#!/bin/sh

cd "$( dirname "$0" )"

php artisan data:trim
php artisan data:garbagecollect
php artisan data:update_entry_count
