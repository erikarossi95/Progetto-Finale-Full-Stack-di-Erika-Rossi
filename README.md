# 📸 Snaply

> La galleria condivisa dei tuoi eventi — gli invitati caricano foto e video con un QR, senza app né registrazione.

Snaply è una **Single-Page Application** che risolve un problema concreto: dopo un evento, le foto restano sparse sui telefoni degli ospiti. Con Snaply l'organizzatore crea un evento, condivide un **link/QR** e ritrova tutti gli scatti in **un'unica galleria**.

Due attori, due esigenze opposte:

- **Organizzatore** → ha un account (registrazione, login, logout), crea e gestisce eventi, vede le gallerie riempirsi.
- **Invitato** → zero attrito: apre il link, carica foto/video, fine. **Nessuna autenticazione.**

---

## 🧱 Stack tecnologico

| Layer | Tecnologia |
|---|---|
| Backend | PHP 8.1+ vanilla + router minimale custom, PDO |
| Database | MySQL 8 / MariaDB 10.4+ |
| Auth | JWT HS256 (`firebase/php-jwt`), password con `password_hash()` (bcrypt) |
| Frontend | Vue 3 (Composition API, `<script setup>`) + Vite |
| Routing FE | Vue Router 4 |
| State FE | Pinia |
| HTTP client | Axios (con interceptor) |
| Styling | Tailwind CSS (brand identity centralizzata in `tailwind.config.js`) |
| QR code | libreria `qrcode` (lato client) |

**Perché vanilla PHP e non un framework:** deployabile su qualsiasi hosting condiviso, zero overhead e pieno controllo sulle API REST. Composer serve solo per `firebase/php-jwt`.

---

## 📁 Struttura del progetto

```
snaply/
├── backend/
│   ├── public/index.php        # entry point + router + serving media
│   ├── src/
│   │   ├── Config/Database.php  # connessione PDO (singleton)
│   │   ├── Core/                # Router, Request, Response, Jwt, Env, Validator
│   │   ├── Middleware/          # AuthMiddleware
│   │   ├── Controllers/         # Auth, Event, Photo, Profile
│   │   └── Models/              # User, Event, Photo
│   ├── uploads/                 # file caricati (gitignored)
│   ├── .htaccess                # rewrite verso public/index.php (Apache)
│   ├── .env.example
│   └── composer.json
├── frontend/
│   ├── src/
│   │   ├── api/axios.js          # istanza Axios + interceptor
│   │   ├── stores/               # auth.js, events.js, ui.js (Pinia)
│   │   ├── router/index.js       # route + navigation guard
│   │   ├── views/                # Landing, Login, Register, Dashboard, EventDetail, Profile, PublicEvent, NotFound
│   │   ├── components/           # Navbar, Toast, EventCard, PhotoGrid, QrShare, UploadDropzone, ...
│   │   └── utils/url.js
│   ├── tailwind.config.js        # brand identity (colori, font, ombre, gradienti)
│   ├── .env.example
│   └── vite.config.js
├── database/schema.sql
└── README.md
```

---

## 🚀 Setup e avvio (passo-passo)

### Prerequisiti
- PHP **8.1+** con estensioni `pdo_mysql`, `fileinfo`
- **Composer**
- **MySQL 8** o **MariaDB 10.4+**
- **Node.js 18+** e npm

### 1. Database

Crea lo schema (database `snaply` + tabelle `users`, `events`, `photos`):

```bash
mysql -u root -p < database/schema.sql
```

> Se hai già un database creato con una versione precedente dello schema, applica le
> migrazioni in `database/migrations/` (es. `mysql -u root -p < database/migrations/001_add_cover_image.sql`
> aggiunge la colonna copertina agli eventi).

### 2. Backend

```bash
cd backend
composer install

# Configura l'ambiente
cp .env.example .env
# Apri .env e imposta DB_USER/DB_PASS e un JWT_SECRET robusto.
# Suggerimento per generarlo:
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"

# Assicurati che uploads/ sia scrivibile
mkdir -p uploads && chmod 775 uploads

# Avvia il server di sviluppo (front controller come router)
php -S localhost:8000 -t public public/index.php
```

L'API risponde su `http://localhost:8000/api`. I media caricati vengono serviti dal backend su `http://localhost:8000/uploads/...`.

> **In produzione (Apache):** punta il document root su `backend/` (il `.htaccess` reindirizza tutto a `public/index.php`) oppure direttamente su `backend/public/`. Imposta `display_errors=0`.

### 3. Frontend

```bash
cd frontend
npm install

cp .env.example .env
# VITE_API_BASE_URL=http://localhost:8000/api  (già impostato di default)

npm run dev      # sviluppo su http://localhost:5173
# oppure
npm run build    # build di produzione in dist/
```

Apri **http://localhost:5173**, registra un account e crea il primo evento. Il QR generato punta a `http://<origin-frontend>/e/{slug}`.

### Qualità del codice (lint, formattazione, test)

```bash
# Frontend
cd frontend
npm run lint      # ESLint (eslint-plugin-vue, flat config)
npm run format    # Prettier (riscrive src/)
npm run test      # Vitest (test unitari)

# Backend
cd backend
composer test     # PHPUnit (tests/ — Jwt, Validator, RateLimiter)
```

> ⚠️ **CORS:** il backend accetta solo gli origin elencati in `ALLOWED_ORIGINS` (`.env`). Per il dev è già impostato `http://localhost:5173`.

---

## 🔌 Endpoint API

Base path: `/api` · Envelope: `{ "success": true, "data": {...} }` oppure `{ "success": false, "error": { code, message, fields? } }`

### Autenticazione
| Metodo | Endpoint | Auth | Descrizione |
|---|---|---|---|
| POST | `/api/register` | — | Crea l'organizzatore e restituisce subito il token (auto-login) |
| POST | `/api/login` | — | Login, ritorna `user` + `token` |
| POST | `/api/logout` | ✅ | Endpoint di cortesia (JWT stateless, vedi nota sotto) |
| GET | `/api/me` | ✅ | Utente corrente (ripristino sessione al refresh) |

### Eventi (protetti — proprietà verificata, altrimenti 403)
| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/events` | Lista eventi dell'utente con conteggio foto |
| POST | `/api/events` | Crea evento (genera slug univoco) |
| GET | `/api/events/{id}` | Dettaglio evento + foto |
| PUT | `/api/events/{id}` | Aggiorna evento |
| DELETE | `/api/events/{id}` | Elimina evento, foto (cascade) e file fisici |
| POST | `/api/events/{id}/cover` | Carica/sostituisce la copertina (`multipart`, solo immagini JPG/PNG/WebP) |
| DELETE | `/api/events/{id}/cover` | Rimuove la copertina personalizzata |
| POST | `/api/events/{id}/avatar` | Carica/sostituisce l'avatar dell'evento (`multipart`, immagine). L'emoji avatar si imposta invece via `avatar_emoji` su POST/PUT evento |
| DELETE | `/api/events/{id}/avatar` | Rimuove l'avatar immagine |

### Pubblici (no auth — per gli invitati)
| Metodo | Endpoint | Descrizione |
|---|---|---|
| GET | `/api/public/events/{slug}` | Info evento + foto (senza dati organizzatore) |
| POST | `/api/public/events/{slug}/photos` | Upload foto/video (`multipart/form-data`) |

### Foto e profilo (protetti)
| Metodo | Endpoint | Descrizione |
|---|---|---|
| DELETE | `/api/photos/{id}` | L'organizzatore elimina una foto di un suo evento (moderazione) |
| PUT | `/api/profile` | Aggiorna nome/email/password (regole di sicurezza) |

**Status code:** `200` OK · `201` creato · `400` malformato · `401` non autenticato/credenziali · `403` non proprietario · `404` inesistente · `409` conflitto (email) · `422` validazione · `500` errore server.

---

## 🔐 Sicurezza

- Password con **bcrypt** (`password_hash`), mai restituite né loggate.
- **Query parametrizzate** via PDO (no SQL injection).
- **Validazione server-side autorevole** (+ validazione client per UX).
- **Autorizzazione per risorsa**: un utente non accede agli eventi altrui (`403`).
- Upload: **whitelist MIME verificata dal contenuto** (`finfo`), limite dimensione, **nomi file randomizzati** (no overwrite/path traversal), cartella per evento.
- **CORS** ristretto agli origin di `ALLOWED_ORIGINS`, preflight `OPTIONS` gestito.
- **JWT** firmato HS256 con secret robusto e scadenza.
- Errori **generici** verso il client (niente stack trace).

### Scelte architetturali documentate
- **Logout stateless:** il JWT non ha stato lato server, quindi il logout reale avviene cancellando il token sul client. L'endpoint `/api/logout` esiste per completezza del contratto REST e per un'eventuale futura blacklist di token. È una scelta consapevole.
- **Token in `localStorage`:** più semplice da gestire ma esposto a XSS. Alternativa più sicura = cookie `httpOnly`. Per lo scope del progetto si usa `localStorage`.

---

## 🗺️ Roadmap futura

Evoluzioni fuori dallo scope dell'MVP, già pensate a livello di prodotto:

- **Slideshow "LIVE TV"** in tempo reale per proiettori durante l'evento.
- **Download massivo `.zip`** di tutte le foto.
- **Piani a pagamento** (Free / Plus) con limiti per evento.
- **Moderazione con approvazione** delle foto prima della pubblicazione.
- **Email transazionali** (conferma registrazione, reset password).
- **Multilingua**.
- **Reazioni e commenti** degli invitati sulle foto.

