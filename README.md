# LotusBet — Cassino e Apostas Online (PHP)

Uma aplicação web em PHP para cassino e apostas com integração de pagamentos via Pix (LotusPay) e webhook de jogos (PlayFiver). Inclui painel do usuário, área administrativa, fluxo de cadastro/login com CSRF, controle de saldo, depósitos e retiradas, além de integração opcional com Facebook Pixel.


## Recursos
- Autenticação de usuários por CPF com token de sessão e cookie `auth_token` persistente.
- Proteção CSRF por formulário (tokens distintos por ação).
- Depósitos via Pix (LotusPay) com geração de QR Code e callback seguro (HMAC).
- Retiradas manuais ou automáticas (LotusPay) com callback (HMAC).
- Integração de jogos via webhook do provedor PlayFiver (consulta de saldo e débito/crédito de apostas).
- Envio de eventos para Facebook Pixel/Conversions API (opcional) em depósito aprovado e conclusão de cadastro.
- Painel do usuário para depositar, sacar e visualizar saldo/transações.
- Painel administrativo para gestão do site, branding, cores, bônus e integrações.


## Estrutura do projeto
Principais diretórios e arquivos (parcial):
- `index.php` — Landing page e roteamento básico (autologin por `auth_token`).
- `includes/db.php` — Conexão PDO com MySQL.
- `includes/config.php` — Carrega configurações do site a partir da tabela `bet_adm_config`.
- `dashboard/php/` — Endpoints de depósito (`deposito.php`), retirada (`retirada.php`) e callbacks (`callback_deposito.php`, `callback_retirada.php`).
- `webhook/index.php` — Webhook PlayFiver para consulta/atualização de saldo.
- `php/` — Endpoints de `login.php`, `cadastro.php`, etc.
- `bancoparaimportar.sql` — Script SQL para criar/estruturar o banco de dados.
- `.htaccess` — Regras necessárias para encaminhar o header `Authorization` (obrigatório em muitos ambientes).


## Requisitos
- PHP 8.0+ (recomendado 8.1/8.2/8.3).
- Extensões PHP: `pdo_mysql`, `mbstring`, `json`, `curl`. Em aaPanel, ative também `fileinfo`, `imap`, `mbstring` para uploads e carrossel.
- Servidor web (Apache/Nginx) com suporte a PHP.
- MySQL 5.7+ ou MariaDB equivalente.


## Instalação
1) Crie o banco de dados e usuário MySQL no seu provedor de hospedagem, concedendo todas as permissões ao usuário.

2) Importe o script SQL:
- Acesse o phpMyAdmin, selecione o banco e importe o arquivo `bancoparaimportar.sql` (localizado na raiz do projeto).

3) Configure a conexão com o banco:
- Edite `includes/db.php` e preencha:
  ```php
  $dbname = 'NOMEDOBANCOAQUI';
  $user   = 'NOMEDOUSUARIOAQUI';
  $pass   = 'SENHAAQUI';
  ```

4) Faça o upload dos arquivos do projeto para o diretório público do seu servidor (ex.: `public_html`).

5) Verifique o `.htaccess` na raiz:
- O arquivo já está incluso e contém:
  ```
  RewriteEngine On
  RewriteCond %{HTTP:Authorization} ^(.*)
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
  ```
  Isso é essencial em ambientes (como aaPanel/NGINX reverse com Apache) para que callbacks e requisições com `Authorization` funcionem corretamente.

6) Acesse o sistema:
- Painel administrativo: `https://seusite.com/painel`
- Credenciais padrão:
  - E-mail: `adm@adm.com.br`
  - Senha: `12345678`
  - Altere a senha no primeiro acesso por segurança.


## Configuração do site e integrações
As configurações de branding, links e integrações são carregadas de `bet_adm_config` (linha única com `id = 1`), lidas por `includes/config.php`.

Campos relevantes (colunas com prefixo `bet_`):
- `bet_site_nome`, `bet_site_url`, `bet_logo`, `bet_favicon`, `bet_slider`, `bet_cor`.
- `bet_instagram`, `bet_telegram`.
- `bet_bonus_cadastro`, `bet_bonus_raspadinha`.
- `bet_valor_deposito`, `bet_valor_retirada` (mínimos operacionais).
- `bet_pag_tipo` — 0: retirada manual; 1: retirada automática via LotusPay.
- Integrações:
  - LotusPay: `bet_lotuspay` (token). Também existem parâmetros de split definidos em `includes/config.php` (`$LoginSplit`, `$PorcentagemSplit`).
  - PlayFiver: `bet_playfiver_publico`, `bet_playfiver_secreto`.
  - Facebook: `bet_face_pixel`, `bet_face_token`.

Dica: você pode ajustar esses valores pelo painel admin (se já houver telas para isso) ou diretamente no banco.


## Fluxos principais
- Depósito (`dashboard/php/deposito.php`)
  - Valida CSRF, sanitiza valor, aplica limites, gera cobrança Pix via LotusPay (`/api/v1/cashin/`), registra transação e retorna QR Code.
  - Callback: `dashboard/php/callback_deposito.php` valida HMAC `Lotuspay-Auth` (substituído por assinatura HMAC calculada com o token) e, quando `status === 'Completed'`, credita saldo e marca a transação como `Aprovado`. Se origem for Facebook, dispara evento de Purchase via `includes/facebook_pixel.php`.

- Retirada (`dashboard/php/retirada.php`)
  - Valida CSRF, checa limites e saldo. Se `bet_pag_tipo = 0`, cria transação pendente e debita saldo. Se `bet_pag_tipo = 1`, aciona endpoint LotusPay para pagar via Pix para chave (CPF) do usuário.
  - Callback: `dashboard/php/callback_retirada.php` valida HMAC e, quando `status === 'Completed'`, marca a transação como `Aprovado`.

- Webhook PlayFiver (`/webhook/index.php`)
  - Requer `agent_secret` igual a `bet_playfiver_secreto`.
  - Tipos:
    - `BALANCE`: retorna saldo do usuário.
    - `WinBet`: faz débito/crédito atômico do saldo conforme `txn_type` (`debit_credit` ou `bonus`).


## Segurança
- CSRF: A aplicação usa tokens por formulário (`index.php` gera tokens para contato/login/cadastro/recuperação; endpoints validam com `valida_token_csrf`).
- Senhas: Devem estar armazenadas com `password_hash` (verifique `php/cadastro.php` durante criação do usuário).
- Autenticação persistente: `php/login.php` gera `auth_token` (cookie `httponly`, `secure`, `samesite=Strict`) e salva em `bet_usuarios.bet_token`.
- Callbacks: `callback_deposito.php` e `callback_retirada.php` validam assinatura HMAC baseada no corpo (`php://input`) com o token LotusPay.
- `.htaccess`: Necessário para garantir o repasse do header `Authorization`/assinaturas em alguns ambientes.
 - Endurecimento de diretórios: adicionado `.htaccess` em `imagens/` para impedir execução de PHP e em `logs/` para negar acesso web aos logs.


## Logs de callbacks
Os callbacks de depósito e retirada registram eventos em `logs/callback.log` no formato de linhas JSON.

O que é registrado:
- Tipo do callback (`callback_deposito` ou `callback_retirada`).
- Timestamp ISO (`ts`).
- Mensagem de status (ex.: método inválido, assinatura válida/inválida, processamento aprovado, saldo atualizado).
- Contexto mínimo (IP remoto, tamanho do payload, ID da transação, usuário e valor quando aplicável). Dados sensíveis não são gravados.

Segurança e manutenção:
- O diretório `logs/` possui `.htaccess` negando acesso via HTTP.
- Recomenda-se fazer rotação periódica do arquivo (ex.: logrotate, ou simplesmente copiando o arquivo e recriando) e ajustar permissões de arquivo para leitura/escrita apenas pelo usuário do PHP.


## Dicas para produção
- Configure `display_errors=Off` e logue exceções em arquivo seguro.
- Force HTTPS (redirecionamento no servidor) e garanta cookies com `Secure` e `SameSite` adequados.
- Restrinja IPs de endpoints sensíveis (se viável) e use WAF/CDN.
- Faça backup do banco e do diretório de uploads regularmente.


## Solução de problemas (aaPanel)
Se cadastro não concluir ou saldos não atualizarem após pagamento:
- Crie/valide `.htaccess` com:
  ```
  RewriteEngine On
  RewriteCond %{HTTP:Authorization} ^(.*)
  RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
  ```
- Ative extensões PHP: `fileinfo`, `imap`, `mbstring` (para uploads e carrossel "Maiores ganhos de hoje").


## Desenvolvimento local
- Use um stack como XAMPP/Laragon/Docker com PHP 8.x + MySQL.
- Configure `includes/db.php` apontando para o banco local.
- Importe `bancoparaimportar.sql` no MySQL local.
- Acesse `http://localhost/` (ou a URL do seu virtual host).


## Licença & uso
Este projeto é fornecido “como está”, sem garantia ou responsabilidade alguma. Assim como foi baixado gratuitamente, não há como garantir que funcione perfeitamente ou que seja seguro. Não se responsabilizamos por danos ou problemas que possam ocorrer.


## Créditos
- Gateway: Oficial Gerapix/Gerabet, Desenvolvido por viniciuscambio
- Integração de jogos: PlayFiver.
- PHPMailer incluído no diretório `phpmailer/` para envio de e-mails (configurável via `bet_email_*` em `bet_adm_config`).
