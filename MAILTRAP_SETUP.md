# Mailtrap setup for order confirmation emails

Follow these steps on each computer that runs the shop locally. Each person can use their own Mailtrap account and sandbox credentials. The credentials are configured on that computer; they are not stored in the project.

## 1. Open your Mailtrap sandbox

1. Sign in to your Mailtrap account.
2. Open **Email Testing** and choose your sandbox inbox.
3. Open the inbox's **SMTP Settings**.
4. Select the **PHP** or **Other** configuration if Mailtrap asks for an integration type.
5. Copy the sandbox SMTP username and password. These are not your Mailtrap sign-in details.

## 2. Open Apache's configuration

1. Open the **XAMPP Control Panel**.
2. Click **Config** on the Apache row.
3. Choose **Apache (httpd.conf)**. This should open Apache's configuration file in a text editor.

Alternatively, open `C:\xampp\apache\conf\httpd.conf` directly.

## 3. Add the SMTP settings

At the end of `httpd.conf`, add the following lines. Replace the username and password placeholders with the sandbox values you copied. Do not add angle brackets around them.

```apache
SetEnv MAILTRAP_HOST "sandbox.smtp.mailtrap.io"
SetEnv MAILTRAP_PORT "2525"
SetEnv MAILTRAP_USERNAME "paste-your-sandbox-username-here"
SetEnv MAILTRAP_PASSWORD "paste-your-sandbox-password-here"
SetEnv MAILTRAP_FROM_EMAIL "orders@example.test"
SetEnv MAILTRAP_FROM_NAME "Soigné"
```

Keep the username and password private. Do not send them to anyone, add them to project files, or commit them to Git.

## 4. Save the settings and restart Apache

1. Save `httpd.conf`.
2. Return to the XAMPP Control Panel.
3. Stop Apache, then start Apache again. The new settings are picked up when Apache starts.

## 5. Place a test order

1. Open the shop on your computer.
2. Sign in with a test customer account that has an email address on its profile.
3. Add an in-stock product to the cart and complete checkout.
4. The order should be placed even if email sending is unavailable. If sending fails, checkout will display a message that the confirmation email could not be sent.

## 6. Check your Mailtrap inbox

Open the same sandbox inbox from step 1. The confirmation should appear there with the order number, products, quantities, prices, total, shipping name and address, and payment method.

With **Email Testing / Sandbox**, messages are captured in the Mailtrap inbox; they are not delivered to the customer's real email address. To deliver real emails, configure Mailtrap's **Email Sending** SMTP service instead, using its host, port, and credentials.
