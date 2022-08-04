# Coveo (Search API)

## Introduction

The coveo module manages its dependencies and class loader via
composer. So if you simply downloaded this module from drupal.org you have to
delete it and install it again via composer!

## How it works

This module provides an implementation of the Search API which uses an Coveo Push
search server for indexing and searching. Before enabling or using this
module, you'll have to follow the instructions given in INSTALL.txt first.

# Coveo Quick Setup

#### Create a new source

- Create a new PUSH source, as 'Shared'
- Select to create an API Key
- Make sure to copy your key, it will not be displayed again!

#### Edit API Key permissions

- Got to API Keys and edit your new key
- Click on privileges
- Click on the Content section
- Enable Edit on "Fields"
- Click on the Search section
- Set Execute Queries to allowed

#### Setup Drupal

