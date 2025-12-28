# Family Line

A family timeline and history tracking application that helps you preserve and share your family's stories, milestones, and memories.

## Features

- **Timeline Entries**: Record births, deaths, marriages, milestones, stories, photos, and family traditions
- **Family Members**: Manage family member profiles with relationships (parent, child, spouse, sibling)
- **Smart Entry**: AI-powered natural language parsing - just describe what happened and the app extracts the details
- **Family Tree**: Visualize family relationships
- **Team-Based**: Collaborate with family members on shared timelines
- **Privacy Controls**: Set visibility levels for entries (immediate family, extended family, private)

## Quick Entry Examples

Just type naturally and the AI will parse your entry:

- "Mom's 50th birthday party at Grandma's house"
- "Had a bbq at Karla's house today"
- "John and Sarah got married on June 15, 2020 in Chicago"

## Requirements

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL or PostgreSQL

## Installation

1. Clone the repository:
```bash
git clone https://github.com/zach2825/family-line.git
cd family-line
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Copy the environment file and configure:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure your database in `.env`

5. Run migrations:
```bash
php artisan migrate
```

6. (Optional) Add Anthropic API key for enhanced AI parsing:
```
ANTHROPIC_API_KEY=your-api-key-here
```

7. Build assets and start the development server:
```bash
npm run dev
php artisan serve
```

## Tech Stack

- Backend: PHP with Inertia.js
- Frontend: Vue 3 with Tailwind CSS
- Database: MySQL/PostgreSQL
- AI: Anthropic Claude (optional, for enhanced natural language parsing)

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
