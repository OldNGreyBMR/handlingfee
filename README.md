MODULE
======
Product Handling Fees
# handlingfee
Modified Numinix handling fee to operate on PHP8.1 to PHP 8.3 and interface with Aupost
2025-06-11 BMH

INSTALLATION
============
1. Backup your files and database.
2. In includes/classes/shipping.php from this package, Find codes wrapping with comments like "// bof - handling fee module edit ", and Merge those changes to the same file in your Zen Cart store.
3. Upload all files to your Zen Cart store while maintaining the directory structure (rename YOUR_ADMIN to your custom admin folder name);
4. Go to ADMIN->MODULES->HANDLING FEE and click INSTALL;
5. Create your group names and group handling fee amounts;
6. Set either shipping methods to include or exclude;
7. Install Numinix Product Fields module;
8. Go to ADMIN->CATALOG->CATEGORIES/PRODUCTS and assign your products to your previously created handling fee groups using the Numinix Product Fields module.

UNINSTALL
=========
1. Go to ADMIN->MODULES->HANDLING FEE and click REMOVE;
2. Optionally, delete the package files from your server;
