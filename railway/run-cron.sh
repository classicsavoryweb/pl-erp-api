#!/bin/bash
# Cron service start command: bash railway/run-cron.sh
# Runs the Laravel scheduler in the foreground, firing schedule:run every minute.
php artisan schedule:work
