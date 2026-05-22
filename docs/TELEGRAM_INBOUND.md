# Telegram — lançamentos por mensagem

Envie texto ao bot (ex.: **SassFinancialIQ**) para registrar gastos/receitas na base.

## Exemplo

```
Gasto de 16.000 aporte sociedade Multfilmes GYN
```

Resposta esperada:

```
✅ Gasto registrado (#123)
Valor: R$ 16.000,00
Descrição: Aporte Sociedade Multfilmes Gyn
```

## Requisitos

1. `TELEGRAM_BOT_TOKEN` e `TELEGRAM_BOT_USERNAME` no `.env`
2. Usuário vinculado em `/integrations/notifications` (chat ID salvo após /start + teste)
3. Webhook ativo:

```bash
php artisan telegram:webhook-sync
```

A URL precisa ser **pública** (HTTPS em produção). Em desenvolvimento local use ngrok/cloudflared apontando para `APP_URL`.

## Comandos do bot

- `/start` ou `/help` — instruções
- Texto livre com **gasto**, **despesa**, **receita** + valor

## Duplicatas

Mesmo workspace, tipo, valor, data e descrição similar → não grava de novo.

## Desenvolvimento local

```bash
# Terminal 1 — túnel (exemplo ngrok)
ngrok http 8000

# .env
APP_URL=https://xxxx.ngrok-free.app

php artisan config:clear
php artisan telegram:webhook-sync
php artisan queue:work --queue=notifications
```

Sem fila (`QUEUE_CONNECTION=sync`), o job roda na hora do webhook.
