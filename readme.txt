Shipping Boxes Manager
by Numinix (http://www.numinix.com)

This module creates a management system where Zen Cart store admins can define shipping boxes.  The dimensions and weights assigned to each box are then used to determine the most accurate shipping rates in supported modules.

Installation:
0. Install Numinix Product Fields with the optional Dimensions fields and assign dimensions to each of your products (to add dimensions to attributes, also install Attribute Dimensions).
1. Patch your database by copying and pasting the contents of install.sql into ADMIN->TOOLS->INSTALL SQL PATCHES.
2. Upload all file contained in /catalog/ to your store's root while maintaining your directory structure.
3. Go to ADMIN->TOOLS->SHIPPING BOXES MANAGER and add all of your shipping boxes.  You may want to add extra space to the height of the box to account for flex (if box is overfilled).
4. Go to ADMIN->CONFIGURATION->SHIPPING BOXES MANAGER CONFIGURATION and enable the module.
5. Optional: If you would like to use package dimensions in your rate quotes, upload the ups.php and usps.php modules.  Refer to these files for edits that would need to be made to other third party mods in order to support dimensions.

Uninstallation:
1. Remove all files uploaded during the installation.
2. Unpatch your database by copying and pasting the contents of uninstall.sql into ADMIN->TOOLS->INSTALL SQL PATCHES.