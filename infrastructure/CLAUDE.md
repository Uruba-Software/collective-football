# Infrastructure — Collective Football
## `infrastructure/` Dizin Rehberi

> Bu dizin proje altyapısının tamamını barındırır: Docker servisleri, config dosyaları,
> monitoring, CI/CD. Oyun kodu buraya girmez.

---

## Dizin Yapısı

```
infrastructure/
├── docker/
│   ├── php/
│   │   ├── Dockerfile          ← PHP 8.5-fpm-alpine, production image
│   │   └── php.ini             ← PHP ayarları (memory limit, opcache)
│   ├── nginx/
│   │   ├── default.conf        ← Local/Docker nginx (port 8001)
│   │   └── collective-football.urubasoftware.com.conf  ← Production template
│   ├── postgres/
│   │   └── init.sql            ← PostGIS extension init
│   ├── redis/
│   │   └── redis.conf          ← Redis config
│   ├── rabbitmq/
│   │   └── rabbitmq.conf       ← RabbitMQ vhost/plugin config
│   ├── prometheus/
│   │   └── prometheus.yml      ← Scrape targets
│   ├── grafana/
│   │   ├── provisioning/
│   │   │   ├── datasources/    ← Prometheus datasource (auto-provisioned)
│   │   │   └── dashboards/     ← Dashboard provisioning config
│   │   └── dashboards/         ← Dashboard JSON dosyaları
│   ├── sentry/                 ← GlitchTip (Sentry uyumlu) config
│   ├── supervisor/             ← Worker process supervisor config
│   └── zabbix/                 ← Zabbix config (ileride)
└── ssl/                        ← SSL sertifikaları (gitignored)
```

---

## Servis Haritası

### Temel Stack (Her zaman çalışır)
| Container | Image | Port | Amaç |
|-----------|-------|------|------|
| `cf_postgres` | postgis/postgis:16-3.4 | 5432 | Ana veritabanı + coğrafi sorgular |
| `cf_redis` | redis:7-alpine | 6379 | Cache, sessions, queue |
| `cf_rabbitmq` | rabbitmq:3.13-management | 5672, 15672 | Async match simulation queue |
| `cf_app` | custom PHP 8.5 | 9000 (FPM) | Laravel uygulaması |
| `cf_nginx` | nginx:1.25-alpine | 8001→80 | Reverse proxy, static files |

### Monitoring Stack (Phase 0 gereksinimi)
| Container | Image | Port | Amaç |
|-----------|-------|------|------|
| `cf_prometheus` | prom/prometheus:v2.51.0 | 9090 | Metrics toplama |
| `cf_grafana` | grafana/grafana:10.4.0 | 3000 | Metrics görselleştirme |
| `cf_glitchtip` | glitchtip/glitchtip:latest | 8090 | Hata takibi (self-hosted Sentry) |
| `cf_zabbix_server` | zabbix-server-pgsql | 10051 | Infrastructure monitoring |
| `cf_zabbix_web` | zabbix-web-nginx-pgsql | 8888 | Zabbix UI |

### Arama (Phase 1'de aktifleşecek)
| Container | Image | Port | Amaç |
|-----------|-------|------|------|
| `cf_elasticsearch` | elasticsearch:8.13.0 | 9200 | Oyuncu araması, istatistik indeksi |

### Dev-only (--profile dev)
| Container | Image | Port | Amaç |
|-----------|-------|------|------|
| `cf_vue_dev` | node:20-alpine | 5173 | Vue 3 hot-reload dev server |
| `cf_kibana` | kibana:8.13.0 | 5601 | Elasticsearch UI |
| `cf_reverb` | custom PHP 8.5 | 8080 | Laravel Reverb WebSocket (dev) |

---

## PHP Dockerfile — Kritik Notlar

**Image:** `php:8.5-fpm-alpine`

**PHP 8.5-fpm-alpine'de built-in gelenler (kurma!):**
- `mbstring`, `xml`, `opcache`, `pdo`, `tokenizer`, `ctype`, `fileinfo`, `json`

**Kurduklarımız:**
- `pdo_pgsql`, `pgsql` — PostgreSQL bağlantısı
- `zip`, `gd`, `bcmath`, `pcntl`, `intl`, `sockets` — temel ihtiyaçlar
- `redis` (PECL) — Redis bağlantısı
- `amqp` (PECL) — RabbitMQ AMQP protokolü
- `swoole` (PECL) — Octane için async runtime

**Non-root user:** `collective` (uid 1000)

**Yeni extension eklenirken:**
1. PECL mı yoksa `docker-php-ext-install` mı olduğunu kontrol et
2. Alpine dependencies'i (`build-deps`) ekle ve `--virtual` ile kur, sonra sil
3. Aynı `RUN` katmanına koy (image boyutu için)
4. mbstring/xml gibi built-in'leri tekrar ekleme — build hata verir

---

## Nginx Konfigürasyonu

### Local (`default.conf`)
- `resolver 127.0.0.11` — Docker DNS, Reverb başlamamışsa nginx crash olmaz
- Reverb upstream dynamic: `set $reverb_backend reverb;` — graceful startup
- Port 8001: host Apache port 80'i kullandığı için

### Production (`collective-football.urubasoftware.com.conf`)
- Certbot tarafından SSL eklendi (Let's Encrypt, 2026-07-11 expire)
- HTTP → HTTPS 301 redirect
- Rate limiting `/api/` için: `limit_req zone=cf_api burst=50`
- Horizon: sadece localhost erişebilir
- Telescope: tamamen engellendi (403)
- Reverb WebSocket: `proxy_read_timeout 86400` (24 saat)

---

## Prometheus Scrape Targets

`prometheus.yml` şu hedefleri scrape ediyor:
- `nginx:80/metrics` — Laravel app metrics (laravel-prometheus paketi gerektirir)
- `postgres:5432` — PostgreSQL metrics (postgres_exporter gerektirir — Phase 1)
- `redis:6379` — Redis metrics (redis_exporter — Phase 1)
- `rabbitmq:15692` — RabbitMQ built-in Prometheus plugin
- `localhost:9090` — Prometheus self-monitoring

> **Not:** `laravel-prometheus`, `postgres_exporter`, `redis_exporter` Phase 1'de eklenecek.
> Şu an Prometheus çalışıyor ama bazı scrape'ler hata verebilir — sorun değil.

---

## Grafana Provisioning

Grafana başlarken otomatik olarak yükler:
- **Datasource:** `provisioning/datasources/prometheus.yml` → Prometheus bağlantısı
- **Dashboards:** `provisioning/dashboards/` → `dashboards/` klasöründen JSON yükler

**Dashboard eklemek için:**
1. Grafana UI'dan dashboard oluştur
2. Dashboard JSON'unu export et
3. `infrastructure/docker/grafana/dashboards/` klasörüne kaydet
4. Commit'le — tüm geliştiriciler aynı dashboard'u görsün

---

## GlitchTip (Self-hosted Sentry)

GlitchTip Sentry API'si ile uyumlu bir hata takip aracı.

**İlk kurulum (bir kez yapılır):**
```bash
docker compose exec glitchtip ./manage.py createsuperuser
# http://localhost:8090 → Login → Proje oluştur → DSN al
```

**Laravel'e DSN ekleme (Phase 1'de yapılacak):**
```bash
# backend/.env
SENTRY_DSN=http://your-dsn@localhost:8090/1
```

**GlitchTip bağımlılıkları:**
- `cf_sentry_postgres` (PostgreSQL 16) — kendi DB'si
- `cf_redis` (db 1) — Celery task queue için

---

## Zabbix

Infrastructure-level monitoring (sunucu CPU, disk, network).

**İlk giriş:** http://localhost:8888 → Admin / zabbix

**Zabbix Agent kurulumu (Phase 1'de yapılacak):**
```bash
# Hetzner sunucusunda
apt install zabbix-agent2
# /etc/zabbix/zabbix_agent2.conf → Server=127.0.0.1
```

---

## Production Deploy Akışı

```
developer → git push main
    ↓
GitHub Actions CI:
    - php artisan test (PostgreSQL service container)
    - phpstan level 8
    - pint format check
    - npm run build
    - vitest
    ↓ (başarılı)
GitHub Actions Deploy:
    - SSH → 89.167.119.59
    - git pull /opt/uruba/collective-football
    - docker exec cf_app composer install --no-dev -o
    - docker exec cf_app php artisan migrate --force
    - docker exec cf_app php artisan config:cache
    - docker exec cf_app php artisan route:cache
    - docker exec cf_app php artisan view:cache
    - curl /health → 200 kontrol
```

**GitHub Secrets gerekli:**
- `HETZNER_SSH_KEY` — private key içeriği
- `HETZNER_HOST` — 89.167.119.59

---

## Anti-pattern'lar

1. **Port 80 kullanma** — host Apache port 80'de. Docker nginx 8001.
2. **mbstring/xml kurma** — PHP 8.5-fpm-alpine'de built-in. Build hata verir.
3. **docker compose up -d** demeden Reverb başlatma — nginx başlamadan önce Reverb çalışmalı değil, resolver bunu çözüyor.
4. **Production'da APP_DEBUG=true** — docker-compose.prod.yml bunu override ediyor.
5. **vendor/ commit'leme** — .gitignore'da. Hetzner'da `docker exec cf_app composer install` ile kurulur.
6. **Storage dizinlerini unutma** — İlk deploy'da `storage/framework/{cache/data,sessions,views}` ve `bootstrap/cache` manuel oluşturulmalı.

---

## Yeni Servis Eklemek İçin

1. `docker-compose.yml`'e service tanımı ekle
2. Config dosyasını `infrastructure/docker/<servis>/` altına ekle
3. CONNECTIONS.local.md'yi güncelle
4. Bu CLAUDE.md'yi güncelle (Servis Haritası tablosu)
5. Redmine'de issue aç
6. Commit: `feat(infra): add <servis> service`
