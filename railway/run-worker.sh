#!/bin/bash
for i in 1 2 3 4; do
  while true; do
    php artisan queue:work redis --queue=default --sleep=3 --timeout=3900 --tries=1
    sleep 1
  done &
wait