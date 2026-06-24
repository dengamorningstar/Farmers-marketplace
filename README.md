# AgroMarket: Web-Based Farmers Marketplace Platform

A responsive web application designed to optimize agricultural produce distribution, modeled around the logistics and supply chain framework of Twiga Foods. This platform connects local farmers directly with buyers, minimizing post-harvest losses and ensuring fair pricing.

## 🚀 Key Features
* **Safaricom M-Pesa Integration:** Simulates secure Customer-to-Business (C2B) payments using the Daraja API (STK Push).
* **Ngrok Webhook Tunneling:** Exposes the local development environment securely to internet traffic, allowing real-time Daraja payment callback validation.
* **Dynamic Content:** Full CRUD operations for product listings, order processing, and transaction status updates.
* **Secure Authentication:** User role separation (Farmers, Buyers, delivery personnel and Administrators) backed by session management.

## 🛠️ Tech Stack & Tools
* **Backend:** PHP (Object-Oriented approaches)
* **Frontend:** JavaScript (Client-side interactivity), HTML5, CSS3
* **Database:** MySQL (Managed via phpMyAdmin)
* **Local Server Environment:** XAMPP & Apache HTTP Server
* **API Development Tools:** Ngrok, Safaricom Daraja Sandbox Portal
* **IDE:** Visual Studio Code

## 📁 Project Architecture & Configuration
The system relies on a central configuration file (`config.php`) to manage system environment variables, file paths, and API endpoints dynamically. 

*Note: For security best practices, the actual `config.php` containing private API tokens is kept secret. Refer to `config.example.php` for the required structure to run this application locally.*

## ⚙️ How It Works (M-Pesa Simulation Workflow)
1. A buyer initiates a checkout request on the marketplace.
2. The system triggers an **STK Push** request to the Safaricom Daraja API using the `STK_PUSH_URL`.
3. Because the application runs on a local XAMPP server, **Ngrok** creates a live public tunnel (`APP_URL`).
4. Safaricom processes the sandbox payment and sends an asynchronous response back to the public Ngrok link, which safely routes it to `actions/callback.php` to update the transaction status.
