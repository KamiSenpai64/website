# Environment Configuration Guide

This document explains how to configure the AstroTarot website using environment variables.

## Overview

All sensitive configuration data (database credentials, SMTP passwords, API keys, etc.) should be stored in a `.env` file instead of being hardcoded in the source code. This enhances security and makes it easy to use different configurations for development, staging, and production environments.

## Setup Instructions

### 1. Create the `.env` File

Copy the `.env.example` file to create your `.env` file:

```bash
cp .env.example .env
```

Or manually create `/backend/.env` with your configuration values.

### 2. Configure Database Settings

Update the database credentials in `.env`:

```env
DB_HOST=localhost        # Your database server hostname
DB_PORT=3306            # MySQL/MariaDB port (default 3306)
DB_NAME=astrotarot_db   # Your database name
DB_USER=astrotarot_user # Your database user
DB_PASSWORD=your_secure_password_here  # Your database password
```

**Important**: Set a strong password for `DB_PASSWORD` in production.

### 3. Configure SMTP Settings (Email)

Update the SMTP settings for sending booking confirmation emails:

```env
SMTP_HOST=smtp.gmail.com              # SMTP server address
SMTP_PORT=587                         # Port (587 for TLS, 465 for SSL)
SMTP_USERNAME=your_email@gmail.com    # Email account username
SMTP_PASSWORD=your_app_password       # App-specific password (not your main password!)
SMTP_ENCRYPTION=tls                   # Encryption type: tls or ssl
SMTP_FROM_EMAIL=your_email@gmail.com  # Sender email address
SMTP_FROM_NAME=Astro Tarot           # Sender display name
SMTP_BCC_EMAIL=your_email@gmail.com   # Optional: receives copy of all emails
```

#### Gmail Setup Instructions

If using Gmail:

1. Enable 2-Factor Authentication on your Google Account
2. Create an **App Password** (not your main password):
   - Go to Google Account Settings → Security
   - Under "App passwords", select "Mail" and "Windows Computer"
   - Google will generate a 16-character password
3. Use this app password as your `SMTP_PASSWORD`

**DO NOT use your main Gmail password** - this is a security best practice.

### 4. Application Settings

```env
APP_ENV=development     # Set to 'production' in production environments
DEBUG=true             # Set to false in production
```

## Important Security Notes

### ⚠️ Never Commit `.env` Files

The `.env` file is **ALREADY added to `.gitignore`** and will never be committed to your repository. This is critical for security.

- ✅ `.env` is ignored (not committed)
- ✅ `.env.example` is committed (serves as a template)
- ❌ Never commit files with real passwords

### Best Practices

1. **Different Configs Per Environment**
   - Use different `.env` files for development, staging, and production
   - Never reuse production credentials in development

2. **Store in Secure Locations**
   - For production servers, use environment management systems
   - Consider using container secrets, CI/CD pipeline secrets, or managed services

3. **Rotate Passwords Regularly**
   - Periodically change database and email passwords
   - Update `.env` when passwords change

4. **Monitor Access**
   - Only give access to `.env` files to authorized team members
   - Review who has access to production credentials

## File Locations

- **Active Configuration**: `/backend/.env` (used by PHP scripts)
- **Template/Example**: `/.env.example` (for version control, shows all available options)
- **.gitignore Entry**: `/.gitignore` (prevents accidental commits)

## Environment Variables Reference

### Database Variables
| Variable | Default | Purpose |
|----------|---------|---------|
| `DB_HOST` | `localhost` | Database server address |
| `DB_PORT` | `3306` | Database server port |
| `DB_NAME` | `astrotarot_db` | Database name |
| `DB_USER` | `astrotarot_user` | Database username |
| `DB_PASSWORD` | (empty) | Database password |

### SMTP Variables
| Variable | Default | Purpose |
|----------|---------|---------|
| `SMTP_HOST` | `smtp.gmail.com` | Email server address |
| `SMTP_PORT` | `587` | Email server port |
| `SMTP_USERNAME` | (none) | Email account username |
| `SMTP_PASSWORD` | (none) | Email account password |
| `SMTP_ENCRYPTION` | `tls` | Encryption type (tls/ssl) |
| `SMTP_FROM_EMAIL` | (none) | Sender email address |
| `SMTP_FROM_NAME` | `Astro Tarot` | Sender display name |
| `SMTP_BCC_EMAIL` | (none) | BCC recipient (optional) |

### Application Variables
| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_ENV` | `development` | Environment type |
| `DEBUG` | `true` | Enable debug mode |

## Troubleshooting

### Error: "SMTP_PASSWORD. Configureaza variabilele de mediu"
- The SMTP password is not set
- Check that `.env` file exists in `/backend/` directory
- Verify `SMTP_PASSWORD` is defined in `.env`

### Error: "Database connection failed"
- Database credentials are incorrect
- Verify `DB_HOST`, `DB_USER`, and `DB_PASSWORD`
- Ensure database server is running
- Check database user has proper permissions

### Email Not Sending
- Verify SMTP credentials are correct
- Check that app-specific password is used (for Gmail)
- Ensure SMTP port and encryption match your email provider
- Check server firewall allows outbound SMTP connections

## Production Deployment

1. **Do NOT copy `.env` to production** - manage separately
2. Use your hosting provider's environment variable management
3. Set different, strong passwords for production
4. Use a password manager or secrets vault to store credentials
5. Enable audit logging for configuration changes
6. Rotate credentials regularly

## Additional Resources

- [PHP dotenv Documentation](https://github.com/vlucas/phpdotenv)
- [Gmail App Passwords](https://support.google.com/accounts/answer/185833)
- [OWASP: Secrets Management](https://owasp.org/www-community/Sensitive_Data_Exposure)
