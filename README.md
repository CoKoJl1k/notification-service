# Notification Service

Микросервис для массовой рассылки уведомлений (SMS/Email) с приоритезацией трафика, дедубликацией и отслеживанием статусов доставки.

## Технологический стек

- **Язык:** PHP 8.2
- **Фреймворк:** Laravel 11
- **База данных:** PostgreSQL 15
- **Брокер сообщений:** RabbitMQ
- **Кэш:** Redis 7
- **Контейнеризация:** Docker + docker-compose

## Архитектура

```
POST /api/notifications/send  ──►  NotificationController
       │
       ├──►  NotificationService (создание записей, дедубликация)
       │
       └──►  Queue (RabbitMQ / Database)
                │
                └──►  Worker (consumes queue)
                        │
                        └──►  NotificationDispatcher
                                │
                                ├──► MockSmsProvider
                                └──► MockEmailProvider
```

### Приоритезация

- **Transactional** (высокий приоритет) — коды доступа, срочные изменения
- **Marketing** (низкий приоритет) — рекламные рассылки

Transactional уведомления обрабатываются вне очереди перед Marketing.

## Быстрый старт

```bash
# Клонировать репозиторий
git clone <repo-url>
cd notification-service

# Создать .env
cp .env.example .env

# Запустить все сервисы
docker-compose up --build

# Приложение будет доступно на http://localhost:8000
```

## API Endpoints

### 1. Массовая отправка уведомлений

```
POST /api/notifications/send
```

**Request Body:**
```json
{
    "channel": "sms",
    "message": "Your verification code is 123456",
    "recipient_ids": ["+380501234567", "+380501234568"],
    "priority": "transactional"
}
```

**Response (201):**
```json
{
    "notification_ids": ["uuid-1", "uuid-2"],
    "count": 2,
    "channel": "sms",
    "priority": "transactional"
}
```

**Параметры:**
| Поле | Тип | Обязательный | Описание |
|------|-----|-------------|----------|
| `channel` | string | да | `sms` или `email` |
| `message` | string | да | Текст сообщения (1-5000 символов) |
| `recipient_ids` | array<string> | да | Массив получателей (1-1000) |
| `priority` | string | да | `transactional` или `marketing` |
| `idempotency_key` | string | нет | Ключ идемпотентности для дедубликации |

### 2. История уведомлений подписчика

```
GET /api/notifications/subscriber/{recipientId}
```

**Response (200):**
```json
{
    "recipient_id": "+380501234567",
    "notifications": [
        {
            "id": "uuid",
            "recipient_id": "+380501234567",
            "channel": "sms",
            "priority": "transactional",
            "status": "delivered",
            "sent_at": "2024-01-01T00:00:00+00:00",
            "delivered_at": "2024-01-01T00:00:05+00:00",
            "created_at": "2024-01-01T00:00:00+00:00"
        }
    ]
}
```

3. **Статус конкретного уведомления**

```
GET /api/notifications/{id}
```

## Статусы доставки

| Статус | Описание |
|--------|----------|
| `queued` | В очереди — сообщение принято и ожидает отправки |
| `sent` | Отправлено — передано шлюзу/провайдеру |
| `delivered` | Доставлено — подтверждено провайдером |
| `discarded` | Отброшено — ошибка доставки, несуществующий номер/email |

## Примеры использования

### Отправка transactional SMS
```bash
curl -X POST http://localhost:8000/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "message": "Your verification code: 1234",
    "recipient_ids": ["+380501234567"],
    "priority": "transactional"
}'
```

### Отправка email с дедубликацией
```bash
curl -X POST http://localhost:8000/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "email",
    "message": "Welcome!",
    "recipient_ids": ["user@example.com"],
    "priority": "marketing",
    "idempotency_key": "welcome-email-user123"
}'
```

### Проверка статуса
```bash
curl http://localhost:8000/api/notifications/subscriber/+380501234567
```

## Запуск тестов

```bash
# В Docker контейнере
docker-compose exec app php artisan test

# Локально (требуются PostgreSQL, Redis)
php artisan test
```

## Гарантии доставки

- **At-least-once:** Сообщения сохраняются в БД перед отправкой в очередь. Worker подтверждает обработку только после успешной отправки.
- **Retry-механизм:** Автоматический повтор (до 3 попыток) при временной недоступности провайдеров.
- **Дедубликация:** Redis-based идемпотентность с TTL 1 час.
