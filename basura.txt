[phases.setup]
nixPkgs = ["php83", "php83Packages.composer", "nodejs_20"]

[phases.install]
cmds = [
  "cd backend && composer install --no-dev --optimize-autoloader",
  "cd frontend && npm install"
]
[phases.build]
cmds = [
  "mkdir -p backend/bootstrap/cache",
  "mkdir -p backend/storage/framework/cache",
  "mkdir -p backend/storage/framework/sessions",
  "mkdir -p backend/storage/framework/views",
  "mkdir -p backend/storage/logs",
  "mkdir -p backend/storage/app/public",
  "chmod -R 775 backend/bootstrap/cache",
  "chmod -R 775 backend/storage",
  "cd frontend && npm run build"
]

[phases.postbuild]
cmds = [
  "cd backend",
  "php artisan migrate --force",
  "php artisan config:cache",
  "php artisan route:cache",      # recomendado
  "php artisan view:cache"        # recomendado
]

[variables]
NIXPACKS_PHP_ROOT_DIR = "backend/public"
