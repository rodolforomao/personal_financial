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
- **Foto ou PDF de comprovante** — OCR extrai dados; responda **SIM** para salvar ou **NÃO** para descartar
- Texto livre com **gasto**, **despesa**, **receita** + valor (salva direto, sem confirmação)

### Fluxo comprovante

1. Envie imagem do PIX/boleto/comprovante (pode colocar legenda na foto, ex.: *Airbnb, residencial oliveiras, nubank, pix* — vírgulas separam contexto: plataforma, empresa/operação, banco)
2. O bot responde com tipo, valor, data, descrição lidos
3. Responda **`SIM`** se estiver correto → grava transação (receita ou gasto)
4. Responda **`NÃO`** → cancela o rascunho

**Importante:** só a imagem **não grava** sozinha — é preciso confirmar com `SIM`. Se a legenda vier em mensagem separada logo após a foto, o bot atualiza o rascunho e pede confirmação de novo.

Comprovantes de **Pix recebido** (você em *Destino* no Nubank) são classificados como **Receita**.

## Duplicatas

Mesmo workspace, tipo, valor, data e descrição similar → não grava de novo.

## Erro ao baixar foto (cURL error 35 — connection reset)

Falha transitória ao baixar o arquivo em `api.telegram.org/file/...`. O servidor tenta de novo automaticamente (3×). Se persistir:

1. Reenvie a foto ao bot
2. Mantha `php artisan telegram:poll` rodando — um update com erro não derruba mais o comando
3. Opcional no `.env`: `TELEGRAM_DOWNLOAD_RETRIES=5` e `TELEGRAM_DOWNLOAD_RETRY_DELAY_MS=1500`

## Erro SSL (cURL error 60)

Se `telegram:webhook-sync` falhar com *unable to get local issuer certificate*:

```bash
# Ubuntu/Debian (recomendado)
echo 'CURL_CA_BUNDLE=/etc/ssl/certs/ca-certificates.crt' >> .env
php artisan config:clear
php artisan telegram:webhook-sync
```

Último recurso só em dev local: `HTTP_VERIFY_SSL=false` no `.env`.

## Desenvolvimento local (sem ngrok)

O Telegram exige **HTTPS** no webhook. Em `127.0.0.1` use **polling** (não precisa de túnel).

### Sem terminal aberto (recomendado)

No `.env`:

```env
TELEGRAM_SCHEDULED_POLL=true
TELEGRAM_SCHEDULED_QUEUE=true
QUEUE_CONNECTION=database
TELEGRAM_ADMIN_CHAT_IDS=SEU_CHAT_ID_NUMERICO
```

Em um único terminal (ou supervisor/systemd):

```bash
./scripts/artisan serve
php artisan schedule:work
```

O scheduler roda `telegram:poll --once` e drena a fila a cada minuto. No bot você também pode enviar **`/poll`** (busca imediata em segundo plano) ou **`/comandos`** para ver tarefas.

Comandos no Telegram (conta vinculada):

| Comando | Quem | O que faz |
|---------|------|-----------|
| `/poll` | todos | 1 ciclo de getUpdates em background |
| `/fila` | admin | `queue:work --stop-when-empty` |
| `/run daily` | admin | `financial:daily` |
| `/run alerts` | admin | `financial:scan-alerts` |
| `/ops` | todos | status .env e processos necessários (sem terminal) |
| `/comandos` | todos | lista comandos disponíveis |
| `/run evolution` | admin | `evolution:webhook-sync` |

Admin = chat ID em `TELEGRAM_ADMIN_CHAT_IDS` ou flag `telegram_admin` nas preferências do usuário.

Com `QUEUE_CONNECTION=sync`, `/poll` e webhooks processam na hora (sem `schedule:work` para fila).

### Terminal dedicado (legado)

```bash
php artisan telegram:poll
```

Teste único:

```bash
php artisan telegram:poll --once
```

> Antes do primeiro poll, remova webhook antigo (se existir):
> `php artisan telegram:webhook-sync --drop`

### Alternativas HTTPS (produção ou teste webhook)

| Ferramenta | Comando |
|------------|---------|
| **cloudflared** (grátis, sem conta ngrok) | `cloudflared tunnel --url http://127.0.0.1:8000` |
| **localtunnel** | `npx localtunnel --port 8000` |
| **ngrok** | conta + `ngrok config add-authtoken ...` + `ngrok http 8000` |

Depois: `php artisan telegram:webhook-sync --url=https://SEU-TUNEL/api/v1/webhooks/telegram`

### Webhook e fila

Por padrão `TELEGRAM_INBOUND_SYNC=true`: o webhook processa o comprovante **na hora** (resposta imediata no Telegram).

Se `TELEGRAM_INBOUND_SYNC=false`, o update vai para a fila `notifications` — é obrigatório rodar:

```bash
php artisan queue:work --queue=notifications
```

Ou use `QUEUE_CONNECTION=sync` no `.env` (tudo síncrono).

O comando `php artisan telegram:poll` sempre processa na hora (sem fila).
