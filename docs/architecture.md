# Collective Football — Teknik Altyapı & Mimari

> Bu belge projenin tüm teknik altyapısını, servis bağlantılarını ve mimari kararları
> açıklar. Hem geliştiriciler hem de yeni başlayanlar için yazılmıştır.

---

## Büyük Resim

```
┌─────────────────────────────────────────────────────────────────────┐
│                        KULLANICI (Browser)                          │
│                     Vue 3 + TypeScript + Canvas                     │
└──────────────────────┬──────────────────────────────────────────────┘
                       │
           ┌───────────┴────────────┐
           │ REST API               │ WebSocket
           │ (Laravel Sanctum)      │ (Laravel Reverb)
           │ /api/v1/*              │ /app/*
           └───────────┬────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────────────┐
│                      BACKEND — Laravel 13 / PHP 8.5                 │
│                                                                     │
│  ┌─────────────────┐   ┌──────────────────────────────────────────┐ │
│  │  Octane/Swoole  │   │  Match Engine (Strategy Pattern)         │ │
│  │  10K+ concurrent│   │  Pipeline: Shot→Duel→Pass→Morale        │ │
│  └─────────────────┘   └──────────────────┬───────────────────────┘ │
│                                           │                         │
│  ┌─────────────────────────────────────── │ ───────────────────┐   │
│  │  Jobs / Queues                         │                     │   │
│  │  HeadlessMatchEngine → RabbitMQ ───────┘                     │   │
│  │  4x Supervisor workers                                       │   │
│  └──────────────────────────────────────────────────────────────┘   │
└──────┬──────────────────┬────────────────────┬───────────────────────┘
       │                  │                    │
  ┌────▼────┐      ┌──────▼──────┐    ┌───────▼───────┐
  │ PostgreSQL│    │    Redis     │    │   RabbitMQ    │
  │ +PostGIS │    │  Cache/Queue │    │  Match Jobs   │
  │ port 5432│    │  port 6379   │    │  port 5672    │
  └──────────┘    └─────────────┘    └───────────────┘

  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
  │Elasticsearch │   │  Prometheus  │   │   Grafana    │
  │  port 9200   │   │  port 9090   │   │  port 3000   │
  │  (Phase 1)   │   │  Metrics     │   │  Dashboard   │
  └──────────────┘   └──────────────┘   └──────────────┘
```

---

## Katman Katman Açıklama

### 1. Frontend Katmanı (Vue 3)

**Teknoloji:** Vue 3 (Composition API) + TypeScript + Pinia + Canvas API

**Ne yapar:**
- Maç motorunu tarayıcıda çalıştırır (tick bazlı, dakika dakika)
- Canvas API ile saha çizer ve oyuncuları hareket ettirir
- Laravel Reverb üzerinden WebSocket ile canlı bağlantı tutar
- Pinia store'larında oyuncu state'lerini takip eder

**Neden Canvas, değil SVG/WebGL?**
- 22 oyuncu + top + animasyonlar için Canvas 60fps verebiliyor
- SVG'de 22 eleman için DOM reflow pahalı
- WebGL çok düşük seviye, Canvas yeterli

---

### 2. API Katmanı (Laravel + Sanctum)

**URL prefix:** `/api/v1/`

**Auth:** Laravel Sanctum (token tabanlı, SPA destekli)

**Temel endpoint'ler:**
```
POST   /api/v1/auth/login          → token döner
GET    /api/v1/user                → mevcut kullanıcı
POST   /api/v1/match/prepare       → maç başlamadan önce tam context paketi
POST   /api/v1/match/result        → maç bittikten sonra sonuç kayıt
GET    /api/v1/match/{id}          → maç detayı
GET    /api/v1/players             → oyuncu listesi
GET    /api/v1/players/{id}        → oyuncu detayı (4 katman)
GET    /api/v1/teams/{id}          → takım detayı
```

**OpenAPI Docs:** http://localhost:8001/docs/api (Scramble tarafından otomatik üretilir)

---

### 3. WebSocket Katmanı (Laravel Reverb)

**Protokol:** WebSocket (ws:// veya wss://)
**Port:** 8080 (Docker internal), 8082 (production host proxy)
**Channels:** Presence channels (kim bağlı bilgisi)

**Ne zaman kullanılır:**
- Multiplayer maçlarda her iki yöneticinin aynı maçı izlemesi
- Maç tick'lerinin frontend'e real-time iletimi
- Bağlantı kopunca 30 saniyelik grace period, sonra abandon

**Neden Reverb, Pusher değil?**
- Self-hosted → maliyet sıfır
- Aynı Laravel içinde → aynı auth, aynı event sistemi
- Pusher uyumlu API → client kütüphanesi değişmez

---

### 4. Match Engine (Strategy Pattern)

Bu projenin kalbi. **Değiştirilmezse hiçbir şey değiştirilmez.**

```
MatchContext (her maç için bir kez oluşturulur)
    ├── home_team (PlayerState × 11)
    ├── away_team (PlayerState × 11)
    ├── referee (hakem özellikleri)
    ├── weather (sıcaklık, nem, rüzgar, yağmur)
    ├── stadium (kapasite, zemin, rakım)
    └── crowd (ev sahibi/deplasman fan sayısı, atmosfer)

Her tick (1 dakika = 1 tick):
    for each event_type:
        Pipeline::through([
            WeatherStrategy,
            ClimateStrategy,
            FatigueStrategy,
            CharacterStrategy,
            FormStrategy,
            CharacterFractureStrategy,
            OutcomeStrategy,
        ])->send(event)
```

**Neden Strategy Pattern?**
- Yeni faktör eklemek = 1 yeni class + pipeline'a kayıt
- Test edilebilir (her strategy izole test)
- Değiştirilebilir (WeatherStrategy'yi değiştir, geri kalan etkilenmez)

---

### 5. Oyuncu Veri Modeli (4 Katman)

```
KATMAN 1: CEILING (player_ceilings tablosu)
    Değişmez. Oyuncu doğduğunda belirlenir.
    overall_potential: 0-100
    position_ceilings: {ST: 85, CM: 72, ...}
    development_speed: fast | normal | slow

KATMAN 2: CHARACTER (player_characters tablosu)
    Yıllara göre değişir (olaylar, koçluk, yaşam deneyimi).
    109 attribute — 6 kategori:
    - Teknik (34): passing, dribbling, finishing...
    - Fiziksel (23): pace, strength, stamina...
    - Mental/Taktik (11): vision, positioning...
    - Kişilik (14): ambition, leadership, consistency...
    - Sosyal/İnsani (10): teamwork, loyalty, charisma...
    - Ahlaki (7): fair_play, self_control, honor...

KATMAN 3: FORM (player_forms tablosu)
    Haftalık güncellenir.
    current_form: 0-100
    confidence_streak: int
    fatigue_cumulative: float
    injury_status: healthy | minor | major | out
    psychological_base: float
    media_pressure: float

KATMAN 4: STATE (DB'ye yazılmaz — sadece maç süresince)
    Dakika bazlı değişir. Frontend takip eder, maç sonu döner.
    current_tempo, concentration, morale, anger_accumulation,
    stress_level, excitement, fatigue_current
```

**Efektif Değer Formülü:**
```
effective = character_value × form_coefficient × state_coefficient
effective = min(effective, position_ceiling[current_position])
```

---

### 6. Veritabanı (PostgreSQL + PostGIS)

**Neden PostgreSQL, MySQL değil?**
- PostGIS extension → stadyum koordinatları, iklim bölgeleri
- JSON tipli sütunlar (player state snapshots)
- Full-text search (MySQL'den güçlü)
- Array tipleri (player ceilings per position)

**PostGIS ne için kullanılıyor?**
- Her stadyumun koordinatı: `stadium.location GEOGRAPHY(Point)`
- İklim bölgesi hesaplaması: stadyum koordinatından sıcaklık/nem/rakım
- Oyuncu uyum hesabı: uyruktan gelen iklim profili × mevcut lokasyon

**Soft Delete politikası:**
- Her tabloda `deleted_at` (SoftDeletes trait)
- **Hiçbir veri hiçbir zaman silinmez**
- Arşivleme: `is_archived = true` + `deleted_at` dolu

---

### 7. Cache & Queue (Redis)

**Redis'in iki rolü:**
1. **Cache:** Config, route, view cache. Oyuncu form verileri (15 dk TTL).
2. **Queue:** Laravel queue jobs (monitoring, notifications)

**RabbitMQ neden ayrı?**
- Match simulation jobs çok uzun sürebilir (90 dk simülasyon = 8 saniye)
- RabbitMQ'nun acknowledgment mekanizması: iş başarısız olursa otomatik retry
- 4 worker aynı anda 4 farklı maçı simulate edebilir
- Redis queue ile bunu yapmak mümkün ama RabbitMQ production-grade

---

### 8. Search (Elasticsearch)

**Şu an kapalı** (Phase 1'de aktifleşecek)

**Ne için kullanılacak:**
- Oyuncu arama (isim, uyruk, pozisyon, rating)
- Maç geçmişi araması
- İstatistik sorguları (en iyi golcüler, form sıralamaları)

**Neden Elasticsearch, PostgreSQL full-text değil?**
- Oyuncu sayısı büyüyünce (binler): Elasticsearch çok daha hızlı
- Faceted search (filtreleme kombinasyonları)
- Aggregations (istatistik hesaplamaları)

---

### 9. Monitoring Stack

#### Prometheus (Metrics Toplama)
- Laravel, PostgreSQL, Redis, RabbitMQ'dan metrics çeker
- 15 saniyede bir scrape
- 30 günlük data saklama
- **Erişim:** http://localhost:9090

#### Grafana (Dashboard)
- Prometheus datasource'u otomatik provisioned
- Dashboards JSON olarak `infrastructure/docker/grafana/dashboards/`'da
- **Erişim:** http://localhost:3000 (admin / collective_admin)

#### GlitchTip (Hata Takibi)
- Self-hosted Sentry alternatifi
- Laravel exception'ları yakalar ve gruplar
- Stack trace, kullanıcı bilgisi, breadcrumb
- **Erişim:** http://localhost:8090

#### Zabbix (Infrastructure Monitoring)
- Sunucu CPU, disk, network, memory izleme
- Alert tanımlamaları (disk %90 → alarm)
- **Erişim:** http://localhost:8888 (Admin / zabbix)

---

### 10. Nginx (Reverse Proxy)

**Local:**
```
Tarayıcı :8001 → cf_nginx → cf_app :9000 (PHP-FPM)
                           → cf_reverb :8080 (/app/* WebSocket)
```

**Production (Hetzner):**
```
Internet :443 → Host Nginx → cf_nginx :8001 → cf_app :9000
                           → Reverb :8082 (/app/* WebSocket)
```

**SSL:** Let's Encrypt (Certbot) — auto-renew aktif, bitiş 2026-07-11

---

## Servis Bağlantı Diyagramı

```
                    ┌─────────────────────┐
                    │    cf_nginx         │
                    │    (nginx:1.25)     │
                    │    Port: 8001       │
                    └──────┬──────────────┘
                           │
              ┌────────────┴────────────┐
              ▼                         ▼
     ┌────────────────┐      ┌──────────────────┐
     │    cf_app      │      │   cf_reverb      │
     │  (PHP 8.5 FPM) │      │ (PHP 8.5 — CLI)  │
     │  Port: 9000    │      │  Port: 8080      │
     └────┬───────────┘      └────────┬─────────┘
          │                           │
     ┌────▼──────────────────────────▼────────┐
     │              cf_redis                   │
     │           (redis:7-alpine)              │
     │              Port: 6379                 │
     └─────────────────────────────────────────┘
          │
     ┌────▼────────────────────────────────────┐
     │            cf_postgres                   │
     │       (postgis/postgis:16-3.4)           │
     │              Port: 5432                  │
     └──────────────────────────────────────────┘
          │
     ┌────▼────────────────────────────────────┐
     │            cf_rabbitmq                   │
     │    (rabbitmq:3.13-management-alpine)     │
     │    AMQP: 5672 | UI: 15672               │
     └──────────────────────────────────────────┘

Monitoring (ayrı subnet ama aynı network):
     cf_prometheus (9090) ←── scrapes ──→ cf_app, cf_postgres, cf_redis
     cf_grafana (3000) ←──── reads ─────→ cf_prometheus
     cf_glitchtip (8090) ←── errors ────→ cf_app (Sentry SDK)
     cf_zabbix_web (8888) ←─ monitors ──→ host server metrics
```

---

## CI/CD Pipeline

```
GitHub Push to main
        │
        ▼
┌───────────────────────────────────────┐
│  GitHub Actions — CI Job              │
│                                       │
│  Backend:                             │
│  ├── composer install                 │
│  ├── php artisan test (PHPUnit)       │
│  ├── ./vendor/bin/phpstan (level 8)   │
│  └── ./vendor/bin/pint --test         │
│                                       │
│  Frontend:                            │
│  ├── npm install                      │
│  ├── npm run type-check (tsc)         │
│  ├── npm test (vitest)                │
│  └── npm run build                    │
└───────────────────────────────────────┘
        │ (başarılı)
        ▼
┌───────────────────────────────────────┐
│  GitHub Actions — Deploy Job          │
│                                       │
│  SSH → 89.167.119.59                  │
│  ├── cd /opt/uruba/collective-football│
│  ├── git pull origin main             │
│  ├── composer install --no-dev -o     │
│  ├── php artisan migrate --force      │
│  ├── php artisan config:cache         │
│  ├── php artisan route:cache          │
│  ├── php artisan view:cache           │
│  └── curl /health → 200 kontrol      │
└───────────────────────────────────────┘
        │ (başarılı)
        ▼
┌───────────────────────────────────────┐
│  GitHub Actions — Auto Approve        │
│  (auto-approve.yml)                   │
│  PR'ı otomatik merge eder             │
└───────────────────────────────────────┘
```

---

## Üçüncü Parti Kütüphaneler

### Laravel Paketleri (Backend)

| Paket | Amaç |
|-------|------|
| `laravel/sanctum` | API token auth, SPA session auth |
| `laravel/reverb` | Self-hosted WebSocket server |
| `laravel/octane` | Swoole üzerinde async PHP runtime |
| `prism-php/prism` | AI entegrasyonu (Claude, GPT, Gemini) |
| `dedoc/scramble` | OpenAPI spec otomatik üretimi |
| `laravel/horizon` | Redis queue monitoring dashboard |
| `laravel/telescope` | Debugging/profiling tool (local) |
| `laravel/pulse` | Real-time performance monitoring |
| `spatie/laravel-data` | Typed DTOs (Data Transfer Objects) |
| `spatie/laravel-query-builder` | API filtreleme/sıralama |
| `php-amqplib/php-amqplib` | RabbitMQ AMQP client |
| `bschmitt/laravel-amqp` | Laravel-RabbitMQ entegrasyonu |
| `roave/security-advisories` | Bilinen güvenlik açığı olan paketleri engeller |

### Frontend Paketleri

| Paket | Amaç |
|-------|------|
| `vue@3` | UI framework (Composition API) |
| `pinia` | State management |
| `typescript` | Tip güvenliği |
| `chart.js` | Grafik/istatistik görselleştirme |
| `d3` | Kompleks data visualizasyon |
| `vite` | Build tool + dev server |
| `vitest` | Unit test framework |
| `playwright` | E2E test framework |

---

## Ortam Değişkenleri (Kritikler)

```bash
# App
APP_KEY=base64:...              # Laravel encryption key
APP_ENV=production              # local | production
APP_DEBUG=false                 # production'da false

# Database
DB_HOST=postgres                # Docker service name
DB_DATABASE=collective_football
DB_USERNAME=collective
DB_PASSWORD=...

# Queue
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_VHOST=collective_football

# WebSocket
REVERB_APP_ID=collective-football
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=0.0.0.0

# AI
ANTHROPIC_API_KEY=...           # Laravel Prism için

# Monitoring
SENTRY_DSN=...                  # GlitchTip DSN (Phase 1'de eklenir)
```

---

## Versiyonlama

```
Format: MAJOR.MINOR.PATCH
→ 0.0.3 şu an (Phase 0, task 3)
→ 0.1.x (Phase 1 — Match Engine)
→ 1.0.0 (Public Launch)

MAJOR: Her zaman 0, launch'ta 1 olur
MINOR: Faz numarası (0, 1, 2, ...)
PATCH: Faz içindeki görev sırası
```

Config: `backend/config/version.php`

---

## Phase 0 — Tamamlanan Altyapı Kontrol Listesi

- [x] Docker Compose — Full Stack (PostgreSQL+PostGIS, Redis, RabbitMQ, Nginx, PHP 8.5)
- [x] Laravel 13 kurulumu + Sanctum API auth
- [x] Versioned API routes (`/api/v1/`)
- [x] GitHub Actions CI/CD (test + deploy pipeline)
- [x] Hetzner production server deploy (89.167.119.59)
- [x] DNS A kaydı (collective-football.urubasoftware.com → 89.167.119.59)
- [x] SSL sertifikası (Let's Encrypt, auto-renew)
- [x] HTTPS canlı: https://collective-football.urubasoftware.com
- [x] Prometheus + Grafana (monitoring)
- [x] GlitchTip (error tracking)
- [x] Zabbix (infrastructure monitoring)
- [x] CLAUDE.md suite (root, backend, engine, models, frontend, docs, infrastructure)
- [x] Architecture Decision Records (ADR-001 → ADR-004)
- [x] Postman collection (her endpoint, test assertions)
- [x] PR template (Redmine link zorunlu)
- [x] Auto-approve workflow
- [x] Semantic versioning config
- [x] CHANGELOG.md

**Sonraki:** Phase 1 — Match Engine (Epic #136)
