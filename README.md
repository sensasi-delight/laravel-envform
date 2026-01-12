# 🧙‍♂️ Laravel EnvForm

![License](https://img.shields.io/badge/license-MIT-blue.svg)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4.svg)
![Laravel](https://img.shields.io/badge/laravel-%5E10.0-ff2d20.svg)

> **Stop guessing your environment variables.**  
> Automatically generate your `.env` file by scanning your configuration files interactively.

---

## 🧐 Why EnvForm?

**The Problem: ".env Fatigue"** 😫

As a Laravel developer, you juggle multiple environments (Local, Testing, Staging).

- You copy `.env.example`, but it's often outdated or missing new keys.
- You manually hunt through `config/*.php` to find that one missing API key.
- You make a typo, and the app crashes 💥.

It's **repetitive**, **boring**, and **error-prone**.

## 💡 The "All-in-One" Philosophy

> **"Why not just use the default values in config files?"**

You could. But we believe in **Explicit Configuration**.

- **No Guesswork**: Stop hunting through 15+ config files to see what's being used.
- **Single Source of Truth**: See your entire environment state in one file.
- **Instant Onboarding**: New team members don't have to guess which "hidden" defaults they need to override.

With **EnvForm**, you get the best of both worlds: a lean config and a fully-documented `.env`.

---

## ✨ Features

- **🔍 Smart Scanning**: Automatically finds `env('KEY', 'default')` usage in your `config` directory.
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

1. **Scan**: The command scans your `config/` folder.
2. **Select**: Choose which config group you want to edit (e.g., `database`).
3. **Input**: Fill in the values. The tool shows you the key, description, and default value.
4. **Save**: Review your progress and save to `.env` when done.

---

## 🤖 How It Works

EnvForm uses static analysis (RegEx) to parse your PHP configuration files. It looks for the standard Laravel `env()` helper pattern:

```php
// config/app.php
'name' => env('APP_NAME', 'Laravel'),
```

It extracts:

- **Key**: `APP_NAME`
- **Default**: `'Laravel'`
- **Context**: Infers descriptions based on key names (e.g., keys containing `_PASSWORD` are identified as secrets).
