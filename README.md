# Advanced Web Tools Framework

## Description

Advanced Web Tools (AWT) is a high-performance, modular PHP framework for building scalable web applications. Built around a package-oriented architecture, AWT encourages developers to organize code into self-contained, reusable units - keeping projects maintainable as they grow.

### Never touch the core again!

With AWT, you can focus on your application's unique logic and features while the framework quietly handles routing, database access, and security behind the scenes. The result is a codebase that stays clean, maintainable, and easy to extend — with up to 90% of the framework's built-in functionality replaceable or overridable through custom packages alone.
Unlike monolithic frameworks such as Laravel or Symfony, AWT operates as a micro-kernel: a thin, stable core that gets out of your way. You can build and deploy full applications without ever modifying the framework itself.
## Features

- **Modular Package System** - Extend the framework and structure your codebase using the `awt_packages` directory, with clear boundaries between concerns.
- **Advanced Routing** - A robust routing manager with middleware support and lazy-loaded controllers for optimal performance.
- **Database Management** - A fluent database layer with a Query Builder, caching support, and built-in query debugging.
- **CLI Utility** - A dedicated CLI tool (`awt`) for managing routes and packages, and running maintenance tasks.
- **Event Dispatcher** - Decouple application logic with a powerful event-driven system.
- **Singleton-Ready Objects** - Register long-lived objects once and reuse them throughout your application, saving memory and reducing execution overhead.
- **Virtual File System (VFS)** - An abstraction layer for file system operations, improving both flexibility and security.
- **Pre-integrated Assets** - Ships with Bootstrap, Font Awesome, and jQuery included out of the box.
- **Controllers** - First-class controller support for organized, reusable request handling.
- **ORM** - A powerful Object-Relational Mapper for expressive, intuitive database interactions.
- **Migration System** - Manage database schema changes with a straightforward migration workflow.
- **Blade Templating Engine** - A clean, expressive templating engine for generating dynamic HTML content.
- **Context Switching** - AWT automatically manages execution context as it moves between packages, with a simple API to access the current package at any time.
- **Caching** - Cache data at any layer to reduce database load and improve response times.
- **Fully Object-Oriented** - Built from the ground up with OOP principles, ensuring clean, predictable, and extensible code.

## Work in Progress

- Comprehensive documentation and developer guides
- Expanded library of built-in CLI commands
- Enhanced security modules and automated testing framework
- Remote package installation system
- Improved error handling and structured logging
- Route and controller caching

## Notes

- **PHP Version** - Requires PHP 8.x or higher.
- **Configuration** - Core settings are managed via `awt_data/config/awt_config.php`.
- **Database** - Configure your database connection in `awt_data/config/awt_db.php`. Relational databases are currently supported.
- **Public Directory** - Point your web server's document root to the `public` folder for proper routing and security.

## Coming Soon

- **Asynchronous Server** - Support for async request handling, enabling faster response times and improved scalability.
- **Request-Informed Runtime** - On subsequent requests, only the relevant controllers will execute - skipping full package initialization for a leaner runtime.
- **WebSockets** - Real-time, bidirectional communication via WebSocket support.

## Maintainers

- ElStefanos