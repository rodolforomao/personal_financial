# WhatsApp — Evolution API (Opção A)

Uma **instância Evolution** no servidor envia alertas para o **número** que cada usuário cadastra em `/integrations/notifications`.

## Variáveis `.env`

```env
WHATSAPP_PROVIDER=evolution
EVOLUTION_API_URL=http://127.0.0.1:8081
EVOLUTION_API_KEY=sua-chave-secreta
EVOLUTION_INSTANCE_NAME=financial-system
EVOLUTION_WEBHOOK_SECRET=
EVOLUTION_STATUS_WORKSPACE_ID=1
```

| Variável | Descrição |
|----------|-----------|
| `WHATSAPP_PROVIDER` | `evolution` (padrão) ou `http` para gateway genérico legado |
| `EVOLUTION_API_URL` | Base da API (sem barra final) |
| `EVOLUTION_API_KEY` | Mesmo valor de `AUTHENTICATION_API_KEY` no container |
| `EVOLUTION_INSTANCE_NAME` | Nome da instância criada no Evolution |
| `EVOLUTION_WEBHOOK_SECRET` | Opcional; se vazio, usa `EVOLUTION_API_KEY` para validar webhooks |
| `EVOLUTION_STATUS_WORKSPACE_ID` | Workspace onde gravamos status em `integration_connections` |

Modo legado HTTP (`WHATSAPP_PROVIDER=http`):

```env
WHATSAPP_API_URL=https://gateway/send
WHATSAPP_API_TOKEN=token
```

## 1. Subir Evolution API (Docker)

```bash
docker compose up -d evolution-api
```

Painel/manager: `http://127.0.0.1:8081/manager` (conforme versão da imagem).

No Laravel em Docker use `EVOLUTION_API_URL=http://evolution-api:8080`.

## 2. Criar instância e QR (admin)

No manager (`http://127.0.0.1:8081/manager`):

1. Login com `EVOLUTION_API_KEY` do `.env`
2. Criar instância `financial-system` (mesmo nome do `.env`)
3. **Número em branco** (recomendado) ou só dígitos: `5561999013675` (55 + DDD + número)
4. **Connect** → escanear QR no WhatsApp (**Aparelhos conectados**)
5. Aguardar status **open**

Se aparecer erro `wavoipToken`: `./scripts/evolution-fix-schema.sh` e tente de novo.

## 3. Configurar Laravel

```bash
cp .env.example .env   # se necessário
# Preencher EVOLUTION_* e rodar:
php artisan config:clear
php artisan evolution:webhook-sync
```

O comando registra o webhook na Evolution. Se o Laravel roda no host e o Evolution no Docker, use:

```env
EVOLUTION_WEBHOOK_PUBLIC_URL=http://host.docker.internal:8000/api/v1/webhooks/whatsapp
```

(`localhost` no `APP_URL` **não** funciona dentro do container.)

Diagnóstico: `php artisan evolution:diagnose`

Laravel no host + Evolution no Docker:

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan evolution:webhook-sync
```

Sem `--host=0.0.0.0`, o `serve` só escuta `127.0.0.1` e o container não consegue POST no webhook.

## 4. Usuário final

1. Acessar **Integrações → Notificações**.
2. Informar número com DDI (ex.: `+55 11 99999-9999`).
3. Escolher **API do sistema**.
4. Marcar **Receber alertas por WhatsApp** → **Salvar** → **Testar WhatsApp**.

### Comprovantes (foto / PDF)

**WhatsApp Business / `@lid`:** algumas mensagens chegam com `remoteJid` terminando em `@lid` (identificador interno), sem o número real. O sistema usa `remoteJidAlt` quando existir; se houver **apenas um** usuário com WhatsApp em Integrações, associa automaticamente a esse número.


1. Envie **foto ou PDF** do comprovante no WhatsApp (com a instância **open**)
2. O bot responde com tipo, valor, data e descrição (OCR)
3. Responda **SIM** para salvar (transação + comprovante anexado) ou **NÃO** para descartar

Requisitos: `WHATSAPP_INBOUND_ENABLED=true`, `php artisan evolution:webhook-sync` (base64 ativo por padrão). Com `WHATSAPP_INBOUND_SYNC=true` (padrão) não precisa de `queue:work` para comprovantes.

Mesmo fluxo no Telegram: `docs/TELEGRAM_INBOUND.md`.

## 5. Testar webhook

```bash
curl -X POST http://127.0.0.1:8000/api/v1/webhooks/whatsapp \
  -H "apikey: SUA_EVOLUTION_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"event":"connection.update","data":{"state":"open"}}'
```

Resposta esperada: `{"ok":true}`. Eventos ficam em `webhook_logs`.

## 6. Primeira mensagem real

Dispare um alerta de teste ou use **Testar WhatsApp** na tela de integrações.

## Troubleshooting

| Sintoma | Ação |
|---------|------|
| `Failed to connect to 127.0.0.1:8081` | Container parado? `docker compose ps evolution-api` — se `Restarting`, veja `docker compose logs evolution-api` |
| `Database provider invalid` | Evolution v2 exige MySQL + Redis no compose (já configurado). Recrie: `docker compose up -d evolution-api` |
| `wavoipToken does not exist` / `integrationSession.update()` | Rode `docker/mysql/fix-evolution-schema.sql` e reinicie `evolution-api` |
| `number does not match pattern` | No manager, deixe número vazio ou use `5561999013675` (55 + DDD + número, só dígitos) |
| QR não aparece no manager (tela preta) | Bug v2.2.3. Rode `./scripts/evolution-reset-qr.sh` ou `docker compose` com imagem `homolog` + cache local (já no compose). Feche instância antiga (UUID) no manager. |
| MySQL já existia antes do compose | Crie o DB manualmente: `docker compose exec mysql mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS evolution; GRANT ALL ON evolution.* TO 'financial'@'%';"` |
| Teste falha | Instância Evolution conectada? `EVOLUTION_API_KEY` igual no `.env` e no Docker? |
| Webhook 401 | Header `apikey` deve coincidir com `EVOLUTION_WEBHOOK_SECRET` ou `EVOLUTION_API_KEY` |
| Número inválido | Use DDI 55 + DDD + número, só dígitos após normalização |
| Laravel em Docker não alcança Evolution | `EVOLUTION_API_URL=http://evolution-api:8080` na rede do compose |

## Roadmap — Opção B (futuro)

Por usuário: QR próprio, `whatsapp_instances`, múltiplas sessões, chatbot/IA. Ver [ROADMAP.md](ROADMAP.md) — Fase 4.
