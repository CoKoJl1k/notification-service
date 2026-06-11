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
       ├──► NotificationService (создание записей с recipient_id = user_id)
       │
       └──► Queue (RabbitMQ / Database)
                │
                └──► Worker (consumes queue)
                        │
                        └──► NotificationDispatcher (загрузка User, получение phone/email)
                                │
                                ├──► MockSmsProvider
                                └──► MockEmailProvider
```

### Приоритезация

- **Transactional** (высокий приоритет) — коды доступа, срочные изменения
- **Marketing** (низкий приоритет) — рекламные рассылки

Transactional уведомления обрабатываются вне очереди перед Marketing.

## База данных пользователей

Номера телефонов хранятся в таблице `users`. В `notifications.recipient_id` сохраняется ID пользователя. При отправке уведомления номер телефона/email подтягивается из таблицы `users` в момент вызова провайдера.

```bash
# Залить тестовых пользователей (выполняется автоматически при --seed)
php artisan db:seed
```

Таблица `users` содержит поля: `id` (auto increment), `name`, `phone`, `created_at`, `updated_at`.

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
    "recipient_ids": [1, 2],
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
| `recipient_ids` | array\<int\> | да | ID пользователей из таблицы `users` (1-1000) |
| `priority` | string | да | `transactional` или `marketing` |

Номера телефонов/email подтягиваются из таблицы `users` в момент отправки через провайдера. Дедубликация автоматическая по (channel + recipient + message) через Redis.

### 2. История уведомлений пользователя

```
GET /api/notifications/subscriber/{userId}
```

`userId` — ID из таблицы `users`.

**Response (200):**
```json
{
    "recipient_id": 1,
    "phone": "+380501234567",
    "notifications": [
        {
            "id": "uuid",
            "recipient_id": "1",
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

### Отправка transactional SMS (пользователь с ID=1)
```bash
curl -X POST http://localhost:8000/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "message": "Your verification code: 1234",
    "recipient_ids": [1],
    "priority": "transactional"
}'
```

### Отправка email (пользователь с ID=2)
```bash
curl -X POST http://localhost:8000/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "email",
    "message": "Welcome!",
    "recipient_ids": [2],
    "priority": "marketing"
}'
```

### Проверка статуса по ID пользователя
```bash
curl http://localhost:8000/api/notifications/subscriber/1
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
