# 🧙‍♂️ Laravel EnvForm

![Tests](https://github.com/sensasi-delight/laravel-envform/actions/workflows/tests.yml/badge.svg)
![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)
![Laravel](https://img.shields.io/badge/laravel-%5E11%20%7C%20%5E12-ff2d20.svg)

> **Stop guessing your environment variables.**  
> Automatically generate your `.env` file by analyzing your configuration files interactively.

---

## 🧐 Why EnvForm?

**The Problem: ".env Fatigue"** 😫

As a Laravel developer, you juggle multiple environments (Local, Testing, Staging).

- You copy `.env.example`, but it's often outdated or missing new keys.
- You manually hunt through `config/*.php` to find that one missing API key.
- You make a typo, and the app crashes 💥.

It's **repetitive**, **boring**, and **error-prone**.

**The Solution:**
**Laravel EnvForm** automates the detective work. It analyzes your config files for the *actual* truth (`env()` calls) and guides you through a safe, interactive setup wizard. No more guessing.

## 💡 The "All-in-One" Philosophy

> **"Why not just use the default values in config files?"**

You could. But we believe in **Explicit Configuration**.

- **No Guesswork**: Stop hunting through 15+ config files to see what's being used.
- **Single Source of Truth**: See your entire environment state in one file.
- **Instant Onboarding**: New team members don't have to guess which "hidden" defaults they need to override.

With **EnvForm**, you get the best of both worlds: a lean config and a fully-documented `.env`.

---

## ✨ Features

- **🔍 Smart Analysis**: Automatically finds `env('KEY', 'default')` usage in your `config` directory using high-fidelity AST analysis.
- **💅 Interactive UI**: Built with [Laravel Prompts](https://laravel.com/docs/prompts) for a sleek, modern developer experience.
- **🧠 Context Aware**:
  - Automatically suggests generating `APP_KEY` if missing.
  - Detects available database/queue connections for dropdown selection.
  - Handles Boolean values (`true`/`false`) with intuitive toggles.
- **🛡️ Safe**: Checks before overwriting existing files and preserves your existing `.env` values when editing.
- **📂 Organized**: Groups variables by their config file (e.g., `database`, `app`, `services`) for logical setup.

---

## 📦 Installation

Install the package via Composer:

```bash
composer require envform/laravel-envform --dev
```

> **Note**: We recommend installing this as a development dependency, as you likely won't need to generate `.env` files in production interactively.

---

## 🚀 Usage

Simply run the artisan command:

```bash
php artisan envform
```

### The Process

1. **Analyze**: The command performs an AST analysis of your `config/` folder.
2. **Select**: Choose which config group you want to edit (e.g., `database`).
3. **Input**: Fill in the values. The tool shows you the key, description, and default value.
4. **Save**: Review your progress and save to `.env` when done.

---

## 🔒 Security & Privacy

We understand that `.env` files contain sensitive information. **Laravel EnvForm** is built with a "Privacy First" architecture:

- **100% Local Processing**: All analysis and file writing happen entirely on your local machine.
- **No Outbound Connections**: This package has **zero** HTTP dependencies (no Guzzle, no Curl). It cannot and does not send your data to any external server.
- **AST Static Analysis**: We use `nikic/php-parser` to traverse your configuration's abstract syntax tree. We never execute your code or load your environment into memory in a way that could be exported.
- **Transparent Execution**: The source code is open and intentionally kept simple so you can audit it yourself.

---

## 🤖 How It Works

EnvForm performs a **Local Static Analysis** using an AST parser to read your PHP configuration files. It looks for the standard Laravel `env()` helper pattern:

```php
// config/app.php
'name' => env('APP_NAME', 'Laravel'),
```

The tool:

1. **Analyzes** your `config/` directory using PHP-Parser.
2. **Extracts** keys and default values from the AST nodes.
3. **Merges** them with your existing `.env` values (if any).
4. **Interactively** prompts you for updates.
5. **Writes** the final result back to your project root.
