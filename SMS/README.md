# BulkVS SMS/MMS Web Console (PHP + Bootstrap 5)

A multi-user, multi-DID SMS/MMS web application designed for BulkVS messaging workflows. This project delivers a modern “inbox” experience for business texting, including inbound webhooks, outbound sending (SMS and MMS with images), contact management, tagging, auditability, and operational reliability via a background send-queue worker.

This repository is intended to be deployable on a standard Linux server using Apache or NGINX + PHP-FPM, backed by MySQL. It is optimized for teams that own multiple DIDs and need role-based access control, a clean threaded UI, and resilient message delivery mechanics.

---

## What this is

This application is a full web console to:

- **View inbound and outbound conversations** per DID
- **Send SMS and MMS** (text + images)
- **Receive inbound SMS/MMS** via BulkVS webhook callbacks
- **Assign users to DIDs**, with view/send permission controls
- **Manage contacts and tags**, so phone numbers become identifiable entities
- **Show real-time-ish toast notifications** for new inbound messages
- **Queue outbound sends** and process them asynchronously with retries/backoff

---

## What this is not

- It cannot generate true “blue messages” on iPhones (iMessage), because iMessage is Apple’s proprietary protocol. This app can **style outbound bubbles in blue** inside the web UI for familiarity, but outbound transport remains SMS/MMS.

---

## Key Features

### Multi-DID Inbox (Role-Based)
- Left sidebar lists only DIDs assigned to the logged-in user.
- Admin can assign DIDs per user and control view/send permissions.
- Conversations are segregated by DID (useful for departments, locations, practice areas, etc.).

### Threaded Conversations
- Conversation view includes:
  - message bubbles (inbound vs outbound)
  - timestamps
  - outbound delivery state placeholders (sent/failed/queued)
  - automatic scrolling, sane message ordering

### SMS + MMS
- Outbound:
  - Send text messages
  - Attach one or multiple images
  - Sends are queued instantly for responsiveness
- Inbound:
  - Receives texts via webhook
  - Saves inbound images to local disk
  - Displays thumbnails in thread view

### Contact + Tagging Model
- Auto-creates contacts on first inbound message from a new number.
- Conversation view includes a **contact editor panel**:
  - display name
  - tag assignment (VIP, Lead, Spam, etc.)
- Conversation list “joins” the contact display name and tags to improve readability.

### Reliability: Send Queue Worker with Retries/Backoff
- UI enqueues outbound messages quickly and returns you to the conversation.
- Worker delivers in the background:
  - exponential backoff
  - jitter
  - max retry cap
  - failure logging

### Notifications (AJAX Toast Popups)
- Polling-based (simple and reliable in commodity hosting).
- Toast shows:
  - from (contact name/number)
  - to (DID + label)
  - timestamp + message snippet
  - “Open” button (direct link into the thread)

### Read Receipts / Unread State
- Per-user unread state stored in `conversation_user_state`.
- When a user opens a conversation, the UI calls `api/conversation_state.php` to:
  - set last seen message id
  - reset unread count for that user

### Media Security
- Attachments are served through `public/media.php` using:
  - tokenized media links (`id + token`)
  - optional login requirement (default enabled)
- MIME allowlist and max-size limits protect the server.

---

## Screens at a glance

- **Login**
- **Inbox**
  - DID sidebar (left)
  - conversation list (right)
- **Conversation**
  - message thread with thumbnails
  - message composer (text + attachments)
  - contact editor (name + tags)
- **Admin**
  - manage users
  - assign DIDs

---

## Architecture Overview

### Message Lifecycle (Inbound)
1. BulkVS POSTs inbound payload to `webhooks/inbound.php?secret=...`
2. System validates webhook secret (and optionally IP allowlist)
3. System normalizes numbers, ensures DID/contact/conversation exist
4. System inserts inbound `messages` row
5. System fetches/stores inbound media (if present) into `storage/media`
6. System increments unread counters:
   - `conversations.unread_count`
   - `conversation_user_state.unread_count` for all users assigned to that DID

### Message Lifecycle (Outbound)
1. User submits message in conversation composer (`public/send.php`)
2. Files are stored locally and tokenized public URLs are generated
3. Message is inserted as `queued`
4. A job is inserted into `send_queue`
5. Worker (`cron/worker_send_queue.php`) processes queue:
   - calls BulkVS send endpoint
   - inserts final outbound status
   - retries with exponential backoff on failure

---

## Requirements

- PHP 8.1+ (8.2 recommended)
  - extensions: `pdo_mysql`, `curl`, `fileinfo`, `mbstring`
- MySQL 8+ (or MariaDB 10.6+)
- Apache (mod_php) OR NGINX + PHP-FPM
- Cron
- Public HTTPS hostname (recommended) for MMS media retrieval

---

## Installation (Detailed)

### 1) Clone the repository
```bash
git clone https://github.com/YOUR_ORG/YOUR_REPO.git
cd YOUR_REPO
