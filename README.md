# phpShort - URL Shortener Platform

## Overview

**phpShort** is a powerful, self-hosted URL shortener platform built to create, manage, and track branded short links. With a modern interface and robust feature set, phpShort enables users to shorten links, customize aliases, set targeting rules, and analyze detailed analytics. It supports custom domains, retargeting pixels, and integrations with payment gateways for subscription-based services.

This project is ideal for developers, marketers, and businesses looking to manage links efficiently while maintaining brand control and gaining insights into audience behavior. Get a free license code : [https://lunatio.de/license](https://lunatio.de/license)[](https://lunatio.de/phpshort/)

## Features

- **Link Management**: Shorten links individually or in bulk, with custom aliases, password protection, expiration dates, and targeting rules (e.g., by country, platform, or language).[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)[](https://api.lunatio.de/phpshort)
- **Spaces**: Organize links into color-coded spaces for easy management.[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)
- **Custom Domains**: Brand your links with custom domains to boost click-through rates by up to 35%.[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)
- **Advanced Analytics**: Track audience insights including referrers, countries, cities, languages, platforms, browsers, and devices. GDPR, CCPA, and PECR compliant.[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)[](https://lunatio.de/phpshort/)
- **Retargeting Pixels**: Integrate with popular retargeting platforms to enhance conversion rates.[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)
- **Export & Sharing**: Export link statistics in CSV format and share links via email, QR codes, or social networks (Twitter, Facebook, Reddit, Pinterest, LinkedIn, etc.).[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)
- **Subscription Plans**: Offer custom plans with monthly/yearly pricing, tax rates, and coupons. Supports payments via PayPal, Stripe, Razorpay, Paystack, Coinbase, Crypto.com, and bank transfers.[](https://lunatio.de/phpshort/)[](https://phpsocial.lunatio.de/phpshort/)
- **API Support**: Manage links, spaces, domains, and pixels programmatically via a RESTful API.[](https://phpshort.lunatio.de/developers/links)[](https://phpshort.lunatio.de/developers)
- **Admin Panel**: Comprehensive dashboard to manage users, payments, plans, tax rates, and website settings.[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)[](https://lunatio.de/phpshort/)
- **Multi-Language Support**: Customize the platform for different languages.[](https://lunatio.de/phpshort/)
- **S3 Storage**: Store user-uploaded files using Amazon S3, DigitalOcean Spaces, Backblaze B2, etc.[](https://lunatio.de/phpshort/changelog)

## Requirements

To run phpShort, ensure your server meets the following requirements:
- PHP 8.x
- MySQL 5.x or 8.x
- Web server (e.g., Apache, Nginx) with the document root set to the `/public` directory
- Cron job support for automated tasks
- SMTP server for email notifications
- (Optional) S3-compatible storage for file uploads

For detailed requirements, refer to the [official documentation](https://lunatio.de/phpshort/documentation).[](https://lunatio.de/phpshort/documentation)

## Installation

1. **Create a MySQL Database**:
   - Create a new MySQL database and assign a user with full privileges.
2. **Upload Files**:
   - Download the phpShort software from [CodeCanyon](https://codecanyon.net/item/phpshort-url-shortener-platform/27526947) or your purchased source.
   - Upload the contents of the `Software` folder to your web server's root (e.g., `public_html` or `example.com`).
   - Ensure the web server’s document root points to the `/public` directory.[](https://lunatio.de/phpshort/documentation)
3. **Run Installation Wizard**:
   - Navigate to `https://your-domain.com/install` and follow the on-screen instructions to configure the database and initial settings.[](https://lunatio.de/phpshort/documentation)
4. **Activate License**:
   - Log in to your user account and go to `https://your-domain.com/admin`.
   - Enter your license key to activate the software.[](https://lunatio.de/phpshort/documentation)
5. **Set Up Cron Job**:
   - In the admin panel, go to **Settings > Cron Job**, copy the provided command, and set up a cron job to run every minute.[](https://lunatio.de/phpshort/documentation)
6. **Configure Email**:
   - In **Admin > Settings > Email**, set the driver to SMTP and enter your SMTP credentials.[](https://lunatio.de/phpshort/documentation)
7. **(Optional) Configure Storage**:
   - For S3-compatible storage, configure settings in **Admin > Settings > Storage** with your provider’s access keys and endpoint.[](https://lunatio.de/phpshort/documentation)

For detailed instructions, see the [official documentation](https://lunatio.de/phpshort/documentation).[](https://lunatio.de/phpshort/documentation)

## Updating

To update phpShort to the latest version:
1. Back up your `.env` configuration file.
2. Upload and replace all files with the new `Software` folder contents.
3. Restore the `.env` file.
4. Navigate to `https://your-domain.com/update` and follow the update wizard.

**Note**: Version 46 and above include timezone support for stats, which may reset previous statistics due to a database restructure.[](https://lunatio.de/phpshort/documentation)[](https://lunatio.de/phpshort/changelog)

## API Usage

phpShort provides a RESTful API to manage links, spaces, domains, and pixels. Example API call to retrieve links:

```bash
curl --location --request GET 'https://phpshort.lunatio.de/api/v1/links' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer {api_key}'
```

For full API documentation, visit [phpShort API Docs](https://phpshort.lunatio.de/developers).[](https://phpshort.lunatio.de/developers/links)

## License

phpShort is a premium software available via a one-time purchase license, providing full source code access and free updates. Choose between:
- **Regular License**: For personal use.
- **Extended License**: For subscription-based SaaS services.

Purchase and licensing details are available at [CodeCanyon](https://codecanyon.net/item/phpshort-url-shortener-platform/27526947).[](https://codecanyon.net/item/phpshort-url-shortener-software/26536593)[](https://lunatio.de/phpshort/)

## Support

- **Documentation**: [https://lunatio.de/phpshort/documentation](https://lunatio.de/phpshort/documentation)[](https://lunatio.de/phpshort/documentation)
- **Contact**: Reach out via [Lunatio Support](https://lunatio.de/contact) for assistance.
- **Community**: Join discussions on [Babiato Forums](https://babia.to) for community-driven support.[](https://babia.to/threads/phpshort-url-shortener-platform-by-lunatio.35442/)

## Contributing

Contributions are welcome! To contribute:
1. Fork the repository.
2. Create a new branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m "Add your feature"`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

Please ensure your code adheres to the project’s coding standards and includes appropriate tests.

## Disclaimer

This README is a community-created guide and not officially affiliated with Lunatio. For official documentation and support, visit [Lunatio](https://lunatio.de/phpshort).[](https://lunatio.de/phpshort/)

---

&copy; 2025 phpShort by [Lunatio](https://lunatio.de). All rights reserved.
