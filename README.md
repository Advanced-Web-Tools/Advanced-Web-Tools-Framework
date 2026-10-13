<div align="center">

<img width="500" alt="Advanced Web Tools Framework" src="https://github.com/user-attachments/assets/0aff0cb0-4993-446b-b66e-f6d18350cd45" />

# Advanced Web Tools

### Build through packages. Scale without touching the core.

A modular PHP framework for extensible web applications, reusable services, and content management systems.

**Package-oriented architecture · Portable deployments · Event-driven extensibility**

[Developer Documentation](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/wiki) · [Download](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/releases) · [Explore the Ecosystem](https://github.com/Advanced-Web-Tools)

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![Architecture](https://img.shields.io/badge/Architecture-Package--oriented-2563EB?style=flat-square)
![Rendering](https://img.shields.io/badge/Rendering-Server--side-2563EB?style=flat-square)

</div>

---

## Your application. Your packages. One foundation.

Advanced Web Tools puts packages at the center of application development.

Build complete applications, introduce new services, or extend existing functionality through self-contained packages. AWT provides the runtime and shared infrastructure; your packages provide the behavior.

Keep application logic separate from the framework, organize features around clear boundaries, and reuse functionality across projects.

## Build once. Deploy as a package.

**Your next deployment can be a single ZIP.**

Develop your application as an AWT package, bundle it into a ZIP archive, and install it on any compatible AWT instance with the required dependencies.

The framework is already there. Ship your application package without transferring the entire framework or rebuilding the installation around it.

Move from development to production, distribute reusable features, or deliver the same application to multiple installations through one portable package.

## Never touch the core again

Your application should grow through features, not framework modifications.

AWT follows a micro-kernel approach: a stable foundation with functionality delivered and extended through packages. Use defined APIs, events, and shared resources to connect independently developed components.

- **Keep upgrades manageable:** Place application code in packages, separate from the framework core.
- **Build for reuse:** Develop functionality that can serve multiple projects.
- **Deploy independently:** Distribute your application as a package ZIP to compatible AWT installations.
- **Extend existing applications:** Introduce features through package integration.
- **Control the experience:** Render pages on the server and deliver HTML to the browser.

## Everything you need, at one place

| Capability | What it brings to your application |
| --- | --- |
| **Package system** | Organize applications, services, and providers into self-contained units inside `awt_packages`. |
| **Portable packages** | Distribute application functionality as ZIP archives for installation on compatible AWT instances. |
| **Routing & middleware** | Define request handling with middleware and lazy-loaded controllers. |
| **Controllers** | Keep request logic organized and reusable. |
| **Database & ORM** | Work with relational data through a fluent Query Builder and object-relational mapping. |
| **Migrations** | Manage database schema changes as your application evolves. |
| **Blade templates** | Build dynamic, server-rendered pages with expressive templates. |
| **Events** | Connect packages and extend behavior through event-driven integration. |
| **Caching** | Reuse data and reduce repeated work across application layers. |
| **Object management** | Register reusable objects and share them within your application. |
| **Package context** | Access the current package as execution moves between components. |
| **Virtual file system** | Work with files through a shared abstraction. |
| **CLI tooling** | Manage packages, routes, and maintenance tasks with the `awt` command. |
| **Frontend assets** | Start with Bootstrap, Font Awesome, and jQuery included. |

## One framework. Multiple possibilities.

**Web applications**  
Build server-rendered applications with routing, templates, database access, and reusable business logic.

**Content management systems**  
Use a package-oriented foundation for themes, editors, administration, and independently developed features.

**Shared services and developer tools**  
Create reusable capabilities that integrate with applications through explicit interfaces and events.

## Get started

AWT requires **PHP 8.3 or newer**.

1. Download AWT from the [releases page](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/releases).
2. Configure the framework in `awt_data/config/awt_config.php`.
3. Configure your database connection in `awt_data/config/awt_db.php`.
4. Point your web server’s document root to `public`.
5. Build your application inside `awt_packages`, or develop it as a separate project with the [AWT Helper plugin for PhpStorm](https://github.com/Advanced-Web-Tools/AWT-Helper-PhpStorm).

### Install packages through the CLI

Extend an existing AWT installation using the `awt` command-line tool.

Prepare your package ZIP, use the package installation command for your AWT version, and configure any settings required by the package. Deploy the same archive to other compatible instances as needed.

Visit the [developer wiki](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/wiki) for CLI commands, package installation instructions, and dependency requirements.

### Easy framework deployment

Install a framework though CLI command using `awt` command-lint tool.

No need to configure verything manually.

### Develop with PhpStorm

Keep your package in its own project with [AWT Helper for PhpStorm](https://github.com/Advanced-Web-Tools/AWT-Helper-PhpStorm).

Use a dedicated development workflow while keeping application code separate from the framework installation. Build your package for distribution when it is ready to deploy.

**Ready to build?** Explore the [developer documentation](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/wiki) for framework concepts, APIs, and package development guides.

## Development roadmap

AWT is actively evolving. Current areas of development include:

- Expanded documentation and developer guides
- Additional built-in CLI commands
- Security improvements and automated testing
- Remote package installation
- Improved error handling and structured logging
- Route and controller caching

Follow [releases](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/releases) for updates, or use [issues](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/issues) to report problems and suggest improvements.

---

<div align="center">

**Part of the [Advanced Web Tools ecosystem](https://github.com/Advanced-Web-Tools)**

Created and maintained by [Stefan Crkvenjakov · ElStefanos](https://github.com/ElStefanos)

[Read the Developer Wiki →](https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/wiki)

</div>
