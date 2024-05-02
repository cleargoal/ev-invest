# About Project
#### This is a demo Laravel 11 project using Filament package for creating user's area.

## Install
#### Completely typical Laravel installation. All packages are in appropriate 'composer.json' and 'package.json' files. As Docker containers is used Laravel Sail package with changed ports to avoid conflicts with other your projects.

#### However, if a conflict does occur you'll need to fix ports in the '.env' file accordingly to free ports in your system.

## Using
#### To use project as a user go to http://localhost:8006/investor in your browser. Choose your role between

- admin [admin@mail.org](admin@mail.org)
- operator  [operator@mail.org](operator@mail.org)
- investor  [investor@mail.org](investor@mail.org)

#### those have a general password 'password' and the following emails in the list above for login.

## Features
#### What this app can.

- the operator can create vehicles, edit them, and mark any vehicle as 'sold'
- the operator can mark investors payment as valid (or confirmed) when payment came to operators bank account
- investor can add payments or ask about rewords.
- system counts total investments and a part in it of every investor. 
- also, system can, accordingly to investor's part, calculate their percentage and income based on the percentage.

#### Application has a wide capability for different reports those will be implemented later.

## Admin
#### There is an admin area, but not developed properly yet. It'll be made later too. 
