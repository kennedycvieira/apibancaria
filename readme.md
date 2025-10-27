# Aplicação de Simulação Bancária

Aplicação que simula operações bancárias, desenvolvida para um teste técnico de programação.

## Ferramentas Utilizadas

*   PHP
*   MySQL
*   PHPUnit
*   Composer
*   Laravel
*   nginx

## Requisitos

*   [Docker](https://docs.docker.com/desktop/)

## Como Rodar a Aplicação

1.  Baixe os arquivos deste repositório e os extraia.
2.  Abra um terminal e navegue até a pasta contendo os arquivos.
3.  Execute os seguintes comandos:

    *   **Observação:** No Linux, você pode precisar utilizar `sudo` antes dos comandos. Caso utilize uma versão antiga do Docker, pode ser necessário substituir `docker compose` por `docker-compose`.

    ```bash
    docker compose up -d --build
    docker compose exec app composer install
    docker compose exec app php artisan key:generate
    docker compose exec app php artisan migrate --seed
    docker compose exec app php artisan db:seed --class=AccountSeeder
    ```

Após a execução dos comandos, a API estará disponível em `http://localhost:8000/api/v1/`.

Foram criadas 4 contas durante o processo de seed, que podem ser utilizadas para testes. Na pasta `postman`, existe uma coleção com exemplos de requisições para a API que pode ser utilizada para testes.

## Endpoints Disponíveis na API

### `/deposit`

**Parâmetros:**

*   `account_number` (string): Número da conta.
*   `amount` (float): Valor a ser depositado.
*   `currency` (string): Moeda a ser depositada.

**Exemplo com cURL:**

```bash
curl --location 'http://localhost:8000/api/v1/deposit' \
--header 'Content-Type: application/json' \
--data '{
    "account_number": "0001",
    "amount": 100.00,
    "currency": "USD"
}'
```

### `/withdraw`

**Parâmetros:**

*   `account_number` (string): Número da conta.
*   `amount` (float): Valor a ser sacado.
*   `currency` (string): Moeda a ser sacada.

**Exemplo com cURL:**

```bash
curl --location 'http://localhost:8000/api/v1/withdraw' \
--header 'Content-Type: application/json' \
--data '{
    "account_number": "0001",
    "amount": 50.00,
    "currency": "USD"
}'
```

### `/balance`

**Parâmetros:**

*   `account_number` (string): Número da conta.

**Exemplo com cURL:**

```bash
curl --location 'http://localhost:8000/api/v1/balance?account_number=0001'
```

## Testes Unitários

Alguns testes unitários foram desenvolvidos utilizando PHPUnit e estão disponíveis na pasta `tests`. Eles podem ser executados com os seguinte comando:

```bash
docker compose exec app php artisan test --coverage-html coverage-report
```
Os resultados estarão na pasta coverage

## Como Encerrar a Aplicação

*   Para encerrar a aplicação, execute o comando:

    ```bash
    docker compose down
    ```

*   Para encerrar a aplicação e remover todos os dados do seu computador, execute o comando:

    ```bash
    docker compose down -v
    ```
