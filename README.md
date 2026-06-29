# MazeQuest

MazeQuest is a modern, interactive, and educational web application that combines the thrill of navigating mazes with intellectual challenges. Solve puzzles, answer questions, and compete with others while learning new subjects!

## Features
- **Educational Gaming**: Encounter quiz checkpoints inside mazes to unlock paths.
- **Multiple Difficulties**: Choose from Beginner, Intermediate, and Pro mazes.
- **Progress Tracking**: Level up, earn XP, and collect daily coins.
- **Global Leaderboard**: Compete with players worldwide for the top rank.
- **Customizable Themes**: Light and dark mode support with sleek UI designs.
- **Role-based Authentication**: Dedicated portals for standard users and administrators.

## Project Architecture
This application strictly follows modern PHP best practices, employing an MVC-inspired directory structure for clean separation of concerns.

```text
/
├── Api/                 # All RESTful API endpoints and JSON responses
├── assets/              # Static assets
│   ├── css/             # Global and page-specific stylesheets
│   ├── images/          # Images, icons, and game assets
│   └── js/              # Client-side JavaScript
├── Components/          # Shared UI elements (header, footer, navbars)
├── Config/              # Configuration files (Database connection)
├── database/            # Database schema and seeders
│   ├── schema/          # SQL dumps
├── Pages/               # UI Views (converted to PHP for templating)
│   ├── Admin/           # Admin portal pages
│   ├── User/            # User-facing pages (dashboard, game, profile)
│   └── index.php        # Main landing page
├── Routes/              # Application routing logic
│   ├── api.php          # API endpoint routes
│   └── web.php          # Web page routes
├── Services/            # Business logic (e.g., AuthService)
├── Utils/               # Global helper functions (url(), asset(), etc.)
├── .htaccess            # Apache configuration for URL rewriting
└── index.php            # Front Controller handling all incoming requests
```

## Technologies Used
- **Backend**: PHP 8+
- **Database**: MySQL / MariaDB (PDO)
- **Frontend**: HTML5, Vanilla CSS3, Vanilla JavaScript
- **Server**: Apache (with mod_rewrite)

## Installation & Setup

1. **Clone the Repository**
   Ensure you have XAMPP, WAMP, or an equivalent LAMP stack installed. Clone this repository into your `htdocs` or `www` directory.
   ```bash
   git clone https://github.com/yourusername/mazequest.git game
   ```

2. **Database Setup**
   - Open phpMyAdmin or your preferred SQL client.
   - Import the schema located at `database/schema/mazequest.sql`.
   - To insert default test users and admins, you can run the provided seeder script:
     ```bash
     php database/setup_db.php
     ```

3. **Configuration**
   - The database connection string is located at `Config/database.php`. Modify the credentials (`$username`, `$password`, `$dbname`) if your local MySQL configuration differs from the defaults.

4. **Apache Configuration**
   - Ensure `mod_rewrite` is enabled on your Apache server for the `.htaccess` routing to function.

## Usage Guide
- **Landing Page**: Navigate to `http://localhost/game/`
- **User Login**: Navigate to `http://localhost/game/user`
- **Admin Login**: Navigate to `http://localhost/game/admin`

*Default seeded credentials (if `setup_db.php` is run):*
- **Admin**: `admin@mazequest.com` / `admin`
- **User**: `user@mazequest.com` / `user`

## Future Improvements
- Migration to a fully-fledged PHP Framework (e.g., Laravel, Symfony).
- WebSocket integration for real-time multiplayer racing.
- Expanded Google Books API integration for dynamic subject generation.

## License
MIT License. See `LICENSE` for more information.

## Author
Created by the MazeQuest Team.
