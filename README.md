# Hızlı Kasa (Woo Quick POS)

A fast, lightweight, and modern WooCommerce Point of Sale (POS) plugin designed for physical store operations. It syncs inventory in real-time, supports barcode scanners, manages multiple warehouses, tracks expenses, and handles receipt printing seamlessly.

## Key Features

*   **Barcode Operations**: Instant barcode search and scanning for rapid checkout.
*   **Advanced Stock & Multi-Warehouse Management**: Allocate stocks to specific depots/warehouses and synchronize inventory automatically.
*   **Offline / Mobile Ready**: Fully optimized interface for mobile devices and tablets, allowing sales on the go.
*   **Receipt Printing**: Custom template support for receipt printers.
*   **Returns & Shipments**: Handle return requests and shipment tracking directly from the POS interface.
*   **Expense Tracking**: Log store expenses directly within the POS dashboard.
*   **Reports & Analytics**: Track sales, stock activity, and store performance.
*   **Robust Architecture**: Modular V2 REST API built using Object-Oriented Programming (OOP) principles, with no-cache enforcement for maximum speed and accuracy.

## Requirements

*   WordPress 5.8 or higher
*   WooCommerce 6.0 or higher
*   PHP 7.4 or higher

## Installation

1.  Download or clone the repository.
2.  Upload the `hizli-kasa` folder to your `/wp-content/plugins/` directory.
3.  Activate the plugin through the 'Plugins' menu in WordPress.
4.  Navigate to **WooCommerce > Hızlı Kasa Settings** in your WordPress dashboard to configure your POS, warehouses, and printer settings.

## Project Structure

The directory layout of the repository is structured as follows:

*   **`.agents/`**: AI agent instructions, rules, and guidelines.
*   **`assets/`**: Assets needed for the POS interface (CSS, JS, libraries, and images).
*   **`includes/`**: Core PHP files of the plugin.
    *   **`includes/api/`**: WooCommerce REST API integration and POS endpoints.
    *   **`includes/classes/`**: OOP helper classes (stock management, hook handler, etc.).
    *   **`includes/views/`**: WordPress admin dashboard panel views.
*   **`scripts/`**: Helper scripts for deployment and repository patching.
*   **`hizli-kasa.php`**: The main entry point file of the plugin (WP Bootstrap).
*   **`[Configurations]`**: Static analysis and refactoring configs at the root level (`phpstan.neon`, `psalm.xml`, `rector.php`).
*   **`composer.json`**: Dependency management and static analysis scripts.

## License

This project is dual-licensed:

1.  **Open Source / Public Use**: Licensed under the **GNU Affero General Public License v3.0 (AGPLv3)**. Any modified versions or network-deployed instances of this software must disclose their full source code under the same terms. See the [LICENSE](file:///LICENSE) file for the full license text.
2.  **Commercial Use**: For proprietary, commercial, or closed-source deployments where AGPLv3 compliance is not desired, a commercial license must be obtained.

For commercial licensing options, custom integrations, or inquiries, please contact the author.

## Author

*   **Seyfullah Kurt** - [GitHub Profile](https://github.com/Seyfullahkurt9)
