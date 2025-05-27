# installation
```
docker compose up -d
docker compose exec csv_backend composer install
docker compose exec csv_backend php artisan migrate
```

# testing
```
php artisan import:customers
```