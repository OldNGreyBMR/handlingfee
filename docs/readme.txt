MODULE
======
Product Handling Fees

DESCRIPTION
===========
This module allows administrators to create handling fee groups and then assign products to these groups. 
Admins have the option of limiting the handling fee to provincial, national, or international customers.
Handling fees can also be restricted to certain shipping modules or excluded from certain shipping modules.

AUTHOR
======
Numinix (sales@numinix.com) http://www.numinix.com

SUPPORT THREAD
==============
http://www.numinix.com/forum/viewtopic.php?t=17

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