# AI Support Chat

A **Laravel 13** backend for a real-time customer support chat system that starts every conversation with an AI agent and lets human support agents seamlessly take over when needed.

---

## Overview

AI Support Chat provides a fully headless JSON API powering a two-sided support experience:

- **Customers** open a conversation anonymously (no account needed) and are immediately served by an AI agent.
- **Human agents** monitor an authenticated dashboard, can take over any conversation in one click, reply directly to customers, and hand back to the AI when done.
- **Real-time updates** — messages, typing indicators, and handover events are all pushed via WebSockets (Laravel Reverb + Laravel Echo/Pusher-js), so both sides always see the latest state without polling.

---

## Architecture

```
┌─────────────────────────────────────────┐
│              REST API (v1)              │
│  /auth   /chat (customer)   /agent      │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│          Service Layer                  │
│  AuthService · ConversationService      │
│  MessageService · AiChatService         │
│  DocumentExtractorService               │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│        Repository Layer                 │
│  ConversationRepository                 │
│  MessageRepository · AttachmentRepo     │
└────────────────┬────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│           Database (MySQL/SQLite)       │
│  users · conversations · messages      │
│  attachments · personal_access_tokens  │
└─────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│         Background Queue                │
│  ProcessAiReply (ShouldQueue job)       │
│  → calls AI provider → broadcasts reply │
└─────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│       AI Provider (pluggable)           │
│  AnthropicProvider (claude-haiku-4-5)  │
│  OpenAiProvider   (gpt-4o)             │
└─────────────────────────────────────────┘
                 │
┌────────────────▼────────────────────────┐
│       File Storage (Cloudinary)         │
│  Images → vision API                   │
│  Documents → optional text extraction  │
└─────────────────────────────────────────┘
```

---

## Key Features

### AI ↔ Human Handover
Every conversation starts in **AI mode**. An agent can take it over (`POST /agent/conversations/{uuid}/takeover`), switching the mode to **human**. While in human mode the AI is silent and the agent replies directly. The agent can release back to AI at any time. If the AI itself decides it cannot handle a request it appends `[ESCALATE]` to its response, which automatically flags the conversation as `pending_handover` and broadcasts the event to all connected agents.

### Real-time Events (Laravel Reverb)
| Event | Channel(s) | Payload |
|-------|-----------|---------|
| `message.sent` | `agent.dashboard`, `conversation.{uuid}` | Full message + attachments |
| `user.typing` | `agent.dashboard`, `conversation.{uuid}` | `sender_type`, `is_typing` |
| `ai.typing` | `conversation.{id}` | `is_typing` |
| `conversation.taken_over` | `agent.dashboard`, `conversation.{uuid}` | Mode, status, assigned agent |
| `conversation.released` | `agent.dashboard`, `conversation.{uuid}` | Mode, status |

### Pluggable AI Provider
Set `AI_PROVIDER=anthropic` or `AI_PROVIDER=openai` in `.env`. Both providers share the same system prompt and support vision (image attachments) and optional document extraction.

### File Attachments
- Up to **5 files per message**, max **10 MB each**.
- Accepted types: `jpg`, `jpeg`, `png`, `gif`, `webp`, `pdf`, `doc`, `docx`, `txt`, `zip`.
- All files are uploaded to **Cloudinary** and stored with full metadata.
- **Images** are passed directly to the AI via the vision API so it can reason about them.
- **Documents** (pdf, doc, docx, txt) are optionally extracted server-side (`DOCUMENT_EXTRACTION_ENABLED=true`) and injected as plain text into the AI context. When disabled (recommended default for sensitive documents), the AI is told only the file name and escalates to a human agent.

### Conversation Statuses & Modes
| | `ai` mode | `human` mode |
|---|---|---|
| `open` | AI is actively replying | Agent is actively replying |
| `pending_handover` | AI flagged for escalation | — |
| `closed` | No new messages accepted | No new messages accepted |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Framework | Laravel 13 (PHP 8.3+) |
| Authentication | Laravel Sanctum (bearer tokens) |
| Real-time | Laravel Reverb (WebSockets) |
| Queue | Laravel Queue (database driver by default) |
| AI — Anthropic | `claude-haiku-4-5-20251001` via HTTP |
| AI — OpenAI | `gpt-4o` via HTTP |
| File storage | Cloudinary |
| PDF parsing | `smalot/pdfparser` |
| DOCX parsing | `phpoffice/phpword` |
| API docs | Scribe |
| Frontend assets | Vite + Tailwind CSS v4 |

---

## Installation

### Prerequisites
- PHP 8.3+
- Composer
- Node.js + npm
- A database (MySQL recommended; SQLite works for local dev)
- A [Cloudinary](https://cloudinary.com) account
- An [Anthropic](https://console.anthropic.com) or [OpenAI](https://platform.openai.com) API key

### Quick Setup

```bash
git clone https://github.com/akineni/ai-support-chat.git
cd ai-support-chat

# One-command setup (installs deps, generates key, runs migrations, builds assets)
composer run setup
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### Seed Demo Agent Accounts

```bash
php artisan db:seed
```

This creates three agent accounts:

| Name | Email | Password |
|------|-------|----------|
| Sarah Support | sarah@support.com | password |
| James Support | james@support.com | password |
| Linda Support | linda@support.com | password |

---

## Environment Variables

Copy `.env.example` to `.env` and fill in the following:

```dotenv
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ai_support_chat
DB_USERNAME=root
DB_PASSWORD=

# AI provider: anthropic or openai
AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-anthropic-key
OPENAI_API_KEY=your-openai-key       # only if AI_PROVIDER=openai

# Cloudinary (required for file attachments)
CLOUDINARY_CLOUD_NAME=your-cloud-name
CLOUDINARY_API_KEY=your-api-key
CLOUDINARY_API_SECRET=your-api-secret

# Optional: extract text from uploaded documents and pass to AI
DOCUMENT_EXTRACTION_ENABLED=false

# Laravel Reverb (WebSockets)
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

---

## Running the Application

```bash
composer run dev
```

This concurrently starts:
- `php artisan serve` — HTTP server
- `php artisan queue:listen` — queue worker (processes AI replies; auto-restarts after code changes, unlike `queue:work`)
- `php artisan reverb:start` — WebSocket server (add `--debug` to log all incoming/outgoing frames)
- `npm run dev` — Vite HMR

---

## API Reference

All endpoints are prefixed with `/api/v1`. A full interactive reference can be generated with Scribe:

```bash
php artisan scribe:generate
```

Then visit `/docs`.

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/login` | — | Agent login → returns bearer token |
| POST | `/auth/logout` | Bearer | Revoke current token |

### Customer Chat (no auth — identified by `session_token`)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/chat` | Start a new conversation |
| GET | `/chat/{sessionToken}/messages` | Fetch message history |
| POST | `/chat/{sessionToken}/messages` | Send a message (+ optional file attachments) |
| POST | `/chat/{sessionToken}/typing` | Broadcast typing indicator |

**Start a conversation:**
```json
POST /api/v1/chat
{
  "customer_name": "John Doe",
  "customer_email": "john@example.com"
}
```
Returns a `session_token` — store this client-side; it identifies all future requests for this conversation.

**Send a message with attachments:**
```
POST /api/v1/chat/{sessionToken}/messages
Content-Type: multipart/form-data

body=Hi, I need help with my order
attachments[]=<file>
```

### Agent Dashboard (Bearer token required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/agent/conversations` | List all conversations (filterable by status) |
| GET | `/agent/conversations/{uuid}/messages` | Fetch conversation history |
| POST | `/agent/conversations/{uuid}/takeover` | Switch to human mode and assign to self |
| POST | `/agent/conversations/{uuid}/release` | Release back to AI |
| POST | `/agent/conversations/{uuid}/reply` | Send agent reply |
| POST | `/agent/conversations/{uuid}/typing` | Broadcast typing indicator |

**Filter conversations by status:**
```
GET /api/v1/agent/conversations?status=pending_handover&per_page=10
```

---

## Real-time Integration (WebSockets)

Using [laravel-echo](https://github.com/laravel/echo) + pusher-js on the frontend:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Subscribe to a conversation channel (customer side)
echo.channel(`conversation.${conversationUuid}`)
    .listen('.message.sent', (e) => console.log('New message:', e.message))
    .listen('.ai.typing', (e) => console.log('AI typing:', e.is_typing))
    .listen('.user.typing', (e) => console.log('Typing:', e))
    .listen('.conversation.taken_over', (e) => console.log('Agent joined:', e))
    .listen('.conversation.released', (e) => console.log('Back to AI:', e));

// Subscribe to agent dashboard channel (agent side)
echo.channel('agent.dashboard')
    .listen('.message.sent', (e) => { /* update conversation list */ })
    .listen('.conversation.taken_over', (e) => { /* update status badge */ });
```

---

## Project Structure

```
app/
├── Enums/                  # ConversationMode, ConversationStatus, MessageSenderType
├── Events/                 # Broadcastable WebSocket events
├── Exceptions/             # ConflictException, NotFoundException, ConversationClosedException
├── Helpers/                # ApiResponse, FileUploadHelper (Cloudinary)
├── Http/
│   ├── Controllers/
│   │   ├── Agent/          # ChatController (authenticated agent endpoints)
│   │   ├── Auth/           # AuthController (login/logout)
│   │   └── Customer/       # ChatController (public customer endpoints)
│   ├── Requests/           # Form request validation
│   └── Resources/          # API resource transformers
├── Jobs/
│   └── ProcessAiReply.php  # Queued job: call AI, broadcast reply, handle escalation
├── Models/                 # Conversation, Message, Attachment, User
├── Providers/
│   └── AppServiceProvider  # Repository interface bindings
├── Repositories/
│   ├── Contracts/          # Repository interfaces
│   └── Eloquent/           # Eloquent implementations
└── Services/
    ├── AiChatService.php           # Provider resolver + escalation logic
    ├── AiProviders/
    │   ├── AiProviderInterface.php
    │   ├── AnthropicProvider.php   # Claude Haiku via Anthropic API
    │   ├── OpenAiProvider.php      # GPT-4o via OpenAI API
    │   └── MessageContextBuilder.php
    ├── AuthService.php
    ├── ConversationService.php
    ├── DocumentExtractorService.php  # PDF/DOCX/TXT text extraction
    └── MessageService.php
```

---

## Testing

Create a dedicated test database first:

```bash
mysql -u root -p -e "CREATE DATABASE ai_support_chat_test;"
```

Then run the suite:

```bash
php artisan test

# Run a specific file
php artisan test tests/Feature/CustomerChatTest.php

# Run a specific test
php artisan test --filter test_agent_can_take_over_a_conversation
```

The suite covers auth, customer chat, agent chat, rate limiting, and AI escalation logic — 40 tests, 105 assertions.

---

## License

MIT