# Operações no servidor — sem vários terminais

Guia único para substituir múltiplos `php artisan` abertos. A mesma informação aparece em:

- Telegram: `/ops`, `/help`, `/comandos`
- Web: `/integrations/notifications` (card “Processos no servidor”)
- Console: após `telegram:webhook-sync` e `evolution:webhook-sync`

## Desenvolvimento local (recomendado)

```bash
php artisan serve
php artisan schedule:work
```

No `.env`:

```env
TELEGRAM_SCHEDULED_POLL=true
TELEGRAM_SCHEDULED_QUEUE=true
QUEUE_CONNECTION=database   # ou sync para processar comprovantes na hora
TELEGRAM_INBOUND_SYNC=true
WHATSAPP_INBOUND_SYNC=true
```

O `schedule:work` executa a cada minuto:

- `telegram:poll --once` (se não usar webhook HTTPS)
- `queue:work --stop-when-empty` (filas notifications, default, ocr, ai)
- `financial:daily` às 06:00

## Produção (Docker)

```bash
docker compose up -d
```

Serviços já definidos: `horizon` (filas contínuas), `scheduler` (`schedule:work`), `evolution-api`.

Setup único:

```bash
php artisan migrate --force
php artisan telegram:webhook-sync    # HTTPS
php artisan evolution:webhook-sync
```

## Pelo Telegram (sem SSH)

| Comando | Descrição |
|---------|-----------|
| `/ops` | Status e o que precisa rodar no servidor |
| `/poll` | Um ciclo de mensagens (dev) |
| `/fila` | Drena fila (admin) |
| `/run daily` | Inteligência diária (admin) |
| `/run alerts` | Varredura de alertas (admin) |
| `/run webhook` | Registra webhook Telegram (admin) |
| `/run evolution` | Registra webhook WhatsApp (admin) |

Admin: `TELEGRAM_ADMIN_CHAT_IDS` no `.env` (IDs numéricos separados por vírgula).

## Modo mais leve

Com `QUEUE_CONNECTION=sync` e webhooks/sync de comprovantes ativos, não é obrigatório `queue:listen` para Telegram/WhatsApp — apenas `schedule:work` se `TELEGRAM_SCHEDULED_POLL=true`.
