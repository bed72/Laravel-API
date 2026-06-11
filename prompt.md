# promp.md — Como rodar o Trocado (runbook)

Passo a passo pra colocar o projeto de pé numa máquina nova. O ambiente é **Laravel Sail**
(Docker): app + Postgres + Valkey.

---

## 0. Pré-requisitos

| O quê | Pra quê | Obrigatório? |
|---|---|---|
| **Docker** rodando | sobe app + Postgres + Valkey | ✅ sim |
| **PHP 8.3+ e Composer** no host | rodar `composer setup` / comandos `composer` | ✅ sim |
| **Node 20+** no host | só pro `composer dev` (`npx concurrently`) | ⚠️ só pro dev loop |

> **Abra o Docker antes de tudo.**

---

## 1. Bootstrap

```bash
composer setup
```

Faz, em ordem: `composer install` → cria `.env` → `sail up -d` (builda a imagem na 1ª vez,
demora alguns minutos) → `key:generate` → `migrate --seed` → instala **Horizon** →
`npm install` + `npm run build`.

> ⚠️ **Corrida com o Postgres:** o `sail up -d` retorna antes do banco aceitar conexão. Se o
> `migrate` falhar com *"connection refused"*, rode `composer setup` de novo (é idempotente).

---

## 2. Ajustar dependências

Três ajustes que ainda não estão no `composer.json`. **Rode no HOST** (ver nota de rede abaixo):

```bash
composer remove laravel/reverb laravel/pao                       # remove deps mortas (Sail FICA)
composer require pestphp/pest --dev --with-all-dependencies      # testes
composer require larastan/larastan --dev                         # análise estática
```

Opcionais: `pestphp/pest-plugin-type-coverage`, `pestphp/pest-plugin-stressless` (teste de carga).

### ⚠️ Se der `curl error 28 ... api.github.com ... Timeout`

É **rede**, não código — o Composer não alcança a API do GitHub pra baixar os zips. Diagnostique:

```bash
curl -sI --max-time 10 https://api.github.com | head -1                       # host
sail exec laravel.test curl -sI --max-time 10 https://api.github.com | head -1 # container
```

- **Container falha, host OK** → rode os comandos `composer` **no host** (sem `sail`); o container
  usa o `vendor/` do host. (É o caso mais comum: container sem saída de internet.)
- **Os dois falham** → VPN/proxy bloqueando `api.github.com`. Opções:
  1. desligar VPN/proxy e tentar de novo;
  2. usar `git` no lugar da API de zip (teu `git` funciona):
     ```bash
     composer config --global preferred-install source
     composer install
     ```
  3. token: `composer config --global github-oauth.github.com <TOKEN>`.

> O `remove` é transacional no `composer.json`/lock: se ele travou no download, as deps certas já
> estão declaradas — basta um `composer install` (com a rede OK) pra completar. Nada a reverter.

---

## 3. Atalho do `sail` (opcional)

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

---

## 4. Verificar

```bash
sail ps                # 3 containers: laravel.test, pgsql, valkey
sail composer test     # suíte Pest (verde)
sail composer lint     # Pint
sail composer analyse  # Larastan (nível 6)
```

No navegador:
- **http://localhost** → app
- **http://localhost/horizon** → dashboard do Horizon

> Nos testes, 2 casos mostram **dívida de propósito**: `POST /api/expenses` retorna **200** (deveria
> ser 201) e usa `user_id = 1` fixo (até o JWT entrar). Documentado no `CLAUDE.md`.

---

## 5. Dia a dia

```bash
composer dev           # containers + Horizon + logs + Vite (precisa Node no host)
```

Equivalente manual (sem Node no host):

```bash
sail up -d
sail artisan horizon   # fila + dashboard
sail npm run dev       # Vite
```

---

## Referência rápida

| Comando | O que faz |
|---|---|
| `composer setup` | bootstrap completo (1ª vez) |
| `composer dev` | containers + Horizon + logs + Vite |
| `sail up -d` / `sail down` | sobe / derruba o stack |
| `sail composer test` | testes (Pest) |
| `sail composer lint` / `analyse` | formata / analisa |
| `sail artisan migrate:fresh --seed` | recria e popula o banco |
| `sail artisan horizon` | worker de fila + `/horizon` |
