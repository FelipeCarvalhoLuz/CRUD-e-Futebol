# CRUD-e-Futebol

Aplicação CRUD de futebol em PHP (XAMPP) com MySQL.

## Como rodar

1. Instale o XAMPP e inicie o Apache e o MySQL.
2. Importe o banco de dados:
   - Abra o phpMyAdmin.
   - Importe o arquivo `db/db.sql` para criar as tabelas e dados de exemplo.
3. Configure a conexão no arquivo `src/config.php` (será criado).
4. Acesse os módulos pelo navegador:
   - `public/teams.php` — Times
   - `public/players.php` — Jogadores
   - `public/matches.php` — Partidas

## Funcionalidades
- CRUD completo para times, jogadores e partidas
- Filtros, paginação, validações e mensagens amigáveis

## Observações
- Não é permitido cadastrar partidas entre o mesmo time.
- Jogadores só podem ter número de camisa entre 1 e 99.
- Exclusão de times bloqueada se houver dependências.

---

Qualquer dúvida, consulte o código ou abra uma issue.
