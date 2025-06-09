# Postmark Inbound Order Status Updater

This is a submission for the [Postmark Challenge: Inbox Innovators](https://dev.to/challenges/postmark).

## What I Built

A lightweight WordPress plugin that allows WooCommerce store admins to update order statuses (like **completed**, **cancelled**, or **refunded**) simply by replying to order confirmation emails. The plugin uses Postmark’s Inbound Webhooks to receive replies and parse intent.

### Features

- Adds a Postmark Inbound email as the reply-to address in WooCommerce admin order confirmation emails.
- Detects reply intent like "cancel", "refund", or "complete/delivered".
- Automatically updates the latest order for that admin accordingly.
- Logs all received replies and status changes securely in a dedicated WordPress database table.
- Includes a built-in admin interface to:
  - Configure inbound email and allowed admin emails.
  - View and manage logs.
  - Clear logs with a button.
- Automatically pulls admin emails for access control.
- Easily extendable for future use cases, such as collecting order reviews via replies.
- Attempts to match order from the original email (extendable to support threading/message-id parsing).

## Demo

🛠️ This plugin runs within a WordPress + WooCommerce installation.

### Testing Instructions

1. Set up a WooCommerce site and install this plugin.
2. Set your Postmark Inbound webhook to:
https://your-site.com/wp-json/pmib/v1/inbound

3. Configure your Postmark Inbound email in the plugin’s settings.
4. Trigger a WooCommerce order email to admin.
5. Reply to that email with one of the following keywords:  
`complete`, `delivered`, `cancel`, or `refund`.
6. The plugin will:
- Authenticate the admin email.
- Parse the reply.
- Change the order status.
- Log and confirm the update via email.

## Code Repository

👉 [GitHub Repository]([https://github.com/yourusername/postmark-inbound-order-status-updater](https://github.com/simplyvikas2017/woocommerce-postmark-inbound-order-status))

> *(Replace the link above with your actual GitHub repo)*

## How I Built It

- **Backend**: WordPress plugin (PHP), REST API, WooCommerce hooks
- **Email handling**: Postmark Inbound API
- **Logging**: Custom SQL table
- **Admin interface**: WordPress Settings API + Menu UI

This project was a great opportunity to explore the power of email-based workflows. Using Postmark's reliable Inbound Webhook system, I was able to eliminate the need for logging into the dashboard just to change order statuses.

It can be further extended to:
- Identify orders by parsing original message headers
- Collect customer reviews by encouraging them to reply to order delivery confirmation emails

---
