# Hei Introduction v1.0b

A feature-rich personal homepage system with integrated profile display, project showcase, blog articles, and online donation modules. Built with a modern frosted-glass design aesthetic, focusing on user experience and performance.

## Features

### Core Display

- **Personal Profile** - Avatar, bio (Markdown format), contact info (QQ / Email)
- **Project Showcase** - Display projects with icons, links, and descriptions; modal detail view
- **Blog System** - Markdown-based article publishing with image upload, live preview, and line-numbered code blocks
- **Typewriter Effect** - Animated typewriter welcome message
- **Scroll Animations** - On-scroll entrance animations for page elements

### Customizable Appearance

- **Color Scheme** - 6 preset gradients (Azure / Sunset / Ocean / Forest / Lavender / Dark Night) + fully custom 3-color gradient with live preview
- **Custom Background** - Three modes:
  - None (default gradient)
  - API Random Image (enter a random image API URL for a different background on each visit)
  - Upload Images (multi-image upload with drag-and-drop, fixed or slideshow mode, configurable 3-60 second interval)
- **Custom Footer HTML** - Write HTML directly in the admin panel to render in the footer area

### Donation System

- **QR Code Donations** - Upload Alipay / WeChat / QQ Wallet payment QR codes
- **Online Payment** - EPay integration with automatic redirect for Alipay / WeChat / QQ Wallet
- **Preset Amounts** - Customizable donation amount buttons
- **Custom Title** - Configurable donation page title and description
- **Homepage Entry** - Donate button appears on the homepage when enabled

### Admin Panel

- **Dashboard** - Daily unique IP count, 7-day visit trend bar chart
- **Site Configuration** - Site name, welcome text, contact info, footer content, color scheme, background settings, donation settings
- **Project Management** - Add / Edit / Delete projects with icon upload (auto WebP conversion)
- **Article Management** - Publish / Edit / Delete articles with built-in Markdown editor and live preview

### Other Highlights

- **One-Click Install** - 3-step setup wizard (Database → Site Settings → Admin Password)
- **Visit Analytics** - IP-deduplicated statistics with visual trend charts
- **Image Optimization** - Automatic WebP conversion via GD library, max width 800px
- **Responsive Design** - Perfectly adapted for desktop / tablet / mobile
- **Zero Frontend Dependencies** - Pure vanilla HTML / CSS / JavaScript — no jQuery, Vue, or React

## Tech Stack

- **Backend**: PHP 7.0+ / MySQL 5.6+ / PDO
- **Frontend**: HTML5 / CSS3 / Vanilla JavaScript
- **Image Processing**: GD Library (WebP conversion)
- **Payment**: EPay Standard API (MD5 signature)

## Directory Structure

```
Hei-Introduction/
├── admin.php           # Admin panel (login + management dashboard)
├── api.php             # AJAX API endpoints
├── article.php         # Article detail page
├── config.php          # Core configuration and utility functions
├── config.inc.php      # Database config (auto-generated after install)
├── donate.php          # Donation page
├── functions.php       # Helper functions
├── index.php           # Homepage
├── install.php         # 3-step installation wizard
├── me.md               # Personal bio (Markdown format)
├── style.css           # Frontend global styles
├── uploads/            # Uploaded files directory
│   ├── projects/       # Project icons
│   ├── background/     # Background images
│   └── donate/         # Donation QR codes
├── picture/            # Article images directory
└── .installed          # Installation marker (auto-generated)
```

## Installation

### Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- GD Library extension (usually bundled with PHP)
- Web server (Apache / Nginx)
- File write permissions

### Setup Steps

1. Download and upload project files to your web server root directory
2. Visit `http://your-domain.com/install.php`
3. Follow the 3-step installation wizard:
   - Step 1: Database configuration (host, database name, username, password)
   - Step 2: Site settings (site name, welcome text, avatar)
   - Step 3: Set admin password
4. You will be automatically redirected to the admin panel after installation

### Security Recommendations

- Delete `install.php` after installation
- Use a strong password for the admin panel
- Back up your database regularly
- Keep PHP and MySQL up to date

## Usage

### Admin Panel

Visit `http://your-domain.com/admin.php` to access the admin panel:

| Module | Description |
|--------|-------------|
| Dashboard | Daily unique IP count, 7-day visit trend chart |
| Site Config | Site name, welcome text, contact info, footer, custom footer HTML, color scheme, background |
| Projects | Add, edit, delete projects with icon upload (auto WebP) |
| Articles | Publish, edit, delete articles with Markdown editor and live preview |
| Donation | Enable/disable, page title & description, QR code upload, EPay integration |

### Color Scheme

Configure in admin panel under "Site Configuration":

- **Preset Gradients**: 6 built-in schemes, click to apply
- **Custom Gradient**: 3 color pickers (background light, primary, accent) with live preview

### Custom Background

Configure in admin panel under "Site Configuration":

- **API Random Image**: Enter a random image API URL
- **Upload Images**: Click or drag-and-drop to upload multiple images; choose fixed or slideshow mode

### Donation Page

Configure in admin panel under "Donation Settings":

1. Enable the donation feature
2. Set page title and description
3. Upload Alipay / WeChat / QQ Wallet payment QR codes
4. Configure EPay parameters (API URL, Merchant PID, Merchant Key)
5. Set preset donation amounts

## Database Schema

| Table | Purpose | Key Fields |
|-------|---------|------------|
| site_config | Site configuration | site_name, welcome_text, avatar_path, qq, email, footer_text, icp, custom_footer_html, color_scheme, background_config, donate_config, admin_password |
| projects | Project management | name, icon_path, link, description |
| articles | Article management | title, content, views |
| visits | Visit analytics | ip, visit_date, count |

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Installation fails | Check PHP version, database credentials, and directory write permissions |
| Image upload fails | Check uploads/ and picture/ directory permissions; ensure GD library is enabled |
| Styles not loading | Clear browser cache; verify style.css loads correctly |
| Database connection error | Check config.inc.php settings; ensure MySQL service is running |
| EPay redirect fails | Verify API URL, PID, and Key are correct; ensure merchant account is approved |

## License

GPLv3 License
