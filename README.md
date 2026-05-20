# ManuBank

Gestão financeira pessoal (extrato, categorias, investimentos, dashboard).

## Uso local (pessoa sem experiência em TI)

### Windows (recomendado)

1. Baixe o código na branch **main**.
2. **Clique duas vezes em `app.bat`** (ou clique com o botão direito → Executar).

Na primeira vez o script pode instalar automaticamente, via Microsoft Store/winget:

- **Node.js** (inclui npm)
- **PHP**
- **Composer**

Isso pode levar vários minutos. Se pedir confirmação do Windows, aceite.

Depois o navegador abre em **http://localhost:8080**.

**Enquanto usa o ManuBank, deixe a janela preta (terminal) aberta.** Fechar a janela = encerrar o app.

### Mac / Linux

No Terminal, dentro da pasta do projeto:

```bash
make app
```

Na primeira vez, se faltar PHP/Node/Composer, o Mac pede instalação manual:

```bash
brew install php composer node
```

Depois rode `make app` de novo.

---

## Preciso deixar algo rodando?

**Sim, mas só uma coisa:** um pequeno servidor local no seu computador (a janela do `app.bat` ou o Terminal do `make app`).

Não é um site na internet — os dados ficam no seu PC (`src/db/finance.sqlite`).

| O que **não** precisa                     | O que **precisa**                           |
| ----------------------------------------- | ------------------------------------------- |
| Dois programas (Vite + PHP) no uso normal | Uma janela aberta (`app.bat` / `make app`)  |
| Configurar npm manualmente no Windows     | Só na 1ª execução (automático no `app.bat`) |
| Internet para usar o app                  | Só para instalar na primeira vez            |

### Existe outro jeito de usar?

- **Uso normal:** `app.bat` / `make app` → interface já compilada, **um servidor** na porta **8080**.
- **Quem programa:** `make dev` → dois servidores (Vite 5173 + PHP 8000), atualiza a tela ao salvar código.
- **Não há** instalador `.exe` nem app que abre sem servidor: é uma aplicação web local, como muitos sistemas internos.

Para **parar** no modo dev em segundo plano: `make stop`. No modo `app.bat`, feche a janela.

---

## Comandos

| Windows       | Mac / Linux                         |
| ------------- | ----------------------------------- |
| `app.bat`     | `make app`                          |
| —             | `make setup` (só prepara, não abre) |
| —             | `make dev` (desenvolvimento)        |
| Fechar janela | `Ctrl+C` ou `make stop`             |

URL: **http://localhost:8080**

---

## Problemas comuns

**Windows: “não é possível executar scripts”**
Use `app.bat` (já contorna isso). Não é necessário abrir o PowerShell manualmente.

**“Interface não compilada”**
Rode `app.bat` ou `make setup` de novo.

**Página não abre**
Espere 1–2 min na primeira vez. Abra manualmente: http://localhost:8080

**Porta em uso**
Feche outra janela do ManuBank ou rode `make stop` (Mac/Linux).

**Instalação automática falhou**
Feche o terminal, abra outro, tente de novo. Ou instale manualmente: [Node.js](https://nodejs.org), [PHP](https://windows.php.net), [Composer](https://getcomposer.org).

---

## Desenvolvedores

- Banco: `src/db/finance.sqlite`
- API: `public/*.php`
- Frontend: `frontend/` → build copia para `public/` (`index.html`, `assets/`)
- Testes: `./vendor/bin/phpunit`

```bash
make dev    # Vite :5173 + PHP :8000
make build  # só recompila a interface
```
