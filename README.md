# CVbankas IT Vacancy Trends & Analytics 📊

[![Tests](https://img.shields.io/badge/PHPUnit-Passing-success)](tests/)
[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](composer.json)
[![Database](https://img.shields.io/badge/Database-SQLite-informational)](data/)
[![Hosting](https://img.shields.io/badge/Hosting-GitHub%20Pages%20(Free)-green)](https://pages.github.com/)

Автоматизированный мониторинг спроса на рынке IT в Литве на основе данных [cvbankas.lt](https://en.cvbankas.lt/?padalinys%5B0%5D=76&min_salary=0&page=1).

Система отслеживает динамику количества вакансий по **основным языкам программирования**, **корпоративным ERP/CRM стекам** и **IT-ролям** с нулевыми затратами на хостинг (100% Free Serverless / GitOps).

---

## 🚀 Архитектура и технологии (Zero-Cost Hosting)

- **Планировщик и раннер:** [GitHub Actions](.github/workflows/daily.yml) запускается по расписанию (`cron: 0 4 * * *`) и по кнопке (`workflow_dispatch`).
- **Парсер и классификатор:** PHP 8.3 + Guzzle + Symfony DomCrawler. Учитывает пагинацию, вежливые паузы (rate-limiting) и нормализацию названий.
- **База данных:** SQLite (`data/cvbankas.sqlite`) хранится и версионируется прямо в Git-репозитории.
- **Веб-дашборд:** [GitHub Pages](web/) со стильным интерактивным графиком на [Chart.js](https://www.chartjs.org/) (тёмная/светлая темы, фильтрация по группам, выбор временных отрезков).
- **Кнопка «Collect Data Now»:** встроенное модальное окно на дашборде позволяет в 1 клик запустить сбор данных через GitHub REST API без ожидания расписания.

---

## 📂 Отслеживаемые категории

1. **Основные языки программирования (Stacks):**
   - JavaScript / TypeScript (включая Node.js, Frontend фреймворки React/Vue/Angular)
   - Python (включая Django, FastAPI, Flask)
   - Java (включая Spring Boot)
   - C# / .NET
   - PHP (включая Laravel, Symfony)
   - C / C++
   - Go
   - Rust
   - Ruby
   - **Мобильные разработчики:** Android (Kotlin/Java), iOS (Swift/SwiftUI)

2. **Корпоративные платформы (Enterprise / ERP / CRM):**
   - SAP / ABAP
   - Salesforce / Apex
   - Microsoft Dynamics 365 / Business Central
   - ServiceNow

3. **IT-роли (Roles):**
   - Product / Project Manager (PM, Product Owner, Scrum Master)
   - QA / Testing (Manual & Automation)
   - DevOps / SRE / Cloud
   - AI Specialist / Machine Learning (Data Science, ML Engineer, LLM)
   - Data / Business Analyst (Data, Business, System Analyst)
   - Helpdesk / IT Support / Sysadmin

---

## 💻 Локальный запуск и разработка

### 1. Установка зависимостей
```bash
composer install
```

### 2. Сбор свежих вакансий с cvbankas.lt
```bash
composer collect
# или напрямую:
php bin/console.php collect
```

### 3. Экспорт данных для веб-интерфейса
```bash
composer build
# или напрямую:
php bin/console.php build
```

### 4. Генерация демонстрационной истории (например, за 120 дней)
```bash
php bin/console.php seed-demo 120
```

### 5. Запуск тестов
```bash
composer test
```

### 6. Запуск локального дашборда
```bash
php -S 127.0.0.1:8080 -t web
```
Откройте [http://127.0.0.1:8080](http://127.0.0.1:8080) в браузере.

---

## ⚙️ Настройка автодеплоя на GitHub

1. Создайте репозиторий на GitHub и отправьте код:
   ```bash
   git init
   git add .
   git commit -m "feat: initial cvbankas vacancy tracker"
   git branch -M main
   git remote add origin git@github.com:<ВАШ_АККАУНТ>/<РЕПОЗИТОРИЙ>.git
   git push -u origin main
   ```
2. В репозитории GitHub перейдите в **Settings** ➔ **Pages**:
   - В разделе **Build and deployment** выберите **Source: GitHub Actions**.
3. Перейдите в **Settings** ➔ **Actions** ➔ **General**:
   - В разделе **Workflow permissions** включите **Read and write permissions**.
4. Готово! GitHub Actions будет ежедневно обновлять данные и публиковать график на бесплатном адресе `https://<ВАШ_АККАУНТ>.github.io/<РЕПОЗИТОРИЙ>/`.

---

## ⚡ Как пользоваться кнопкой «Collect Data Now»

На опубликованном дашборде нажмите **«⚡ Collect Data Now»**:
1. Введите адрес вашего репозитория (например: `drebroff/it-vacancy-chart-lt`).
2. Введите ваш [GitHub Personal Access Token](https://github.com/settings/tokens/new?scopes=repo,workflow&description=CvbankasChartTrigger) (с правом `workflow` или `actions:write`).
3. Токен сохранится исключительно в `localStorage` вашего браузера.
4. Нажмите **Run Workflow Now** — статус отобразит запуск, выполнение и автоматически перезагрузит график со свежими данными!
