Shipping Boxes Manager
by Numinix (http://www.numinix.com)

This module creates a management system where Zen Cart store admins can define shipping boxes.  The dimensions and weights assigned to each box are then used to determine the most accurate shipping rates in supported modules.

Installation:
1. Patch your database by copying and pasting the contents of install.sql into ADMIN->TOOLS->INSTALL SQL PATCHES.
2. Upload all file contained in /catalog/ to your store's root while maintaining your directory structure.
3. Go to ADMIN->TOOLS->SHIPPING BOXES MANAGER and add all of your shipping boxes.  You may want to add extra space to the height of the box to account for flex (if box is overfilled).

Uninstallation:
1. Remove all files uploaded during the installation.
2. Unpatch your database by copying and pasting the contents of uninstall.sql into ADMIN->TOOLS->INSTALL SQL PATCHES.