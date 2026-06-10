<?php

use App\Console\Commands\ConsumeNotificationsCommand;
use App\Console\Commands\RabbitMQConsumerCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ConsumeNotificationsCommand::class)->everyMinute();
