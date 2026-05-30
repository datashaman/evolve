
Why use the multi-site extension?

Radiant is a no-fluff CMS built with Rails. Typically, an instance of Radiant is used to build a single website. Being a Rails application, the resources required to run Radiant are fairly hefty, when compared to PHP for example. The multi-site extension allows you to run more than one site from a single instance of Radiant. This allows you to get more mileage from your server.

Using a single instance of Radiant with the multi-site extension also allows you to share users, snippets and layouts between multiple related sites.
Installation

The multi-site extension can be obtained from github

Note: if you are running Radiant 0.6.9, get the branch from Alno

In the root directory of your radiant website, run the following:

./script/extension install multi_site

(If this fails see the manual installation instructions)

Restart your server, and log in to the admin area. If everything has worked, you should find an additional “Sites” tab in the main navigation.
Usage

Let’s begin by assuming that your instance of Radiant already runs a single site. Before we create any new sites, we should register the existing site. The first thing we need to do, is take a note of the id of the current Home page. Click on the ‘Pages’ tab, then click on the ‘Home page’ to edit it. Now look at the path in the address bar. It should be something like:

/admin/pages/edit/1

Take a note of the number at the end of the URL (in this case ‘1’). That is the id of the homepage, and you will need it later.

Now, click on the ‘Sites’ tab, then click the ‘New Site’ button at the bottom of the page. You will find a form with the following fields:

Name:
    The name of the website. You might choose to use descriptive names, such as “Main site”, “Intranet” and “Extranet”, or it may suit your style to just use the domain names.
Domain pattern:
    A regular expression (as used in Ruby, but without the delimiting // symbols). If the domain name used for the incoming request matches the pattern specified here, then Radiant will attempt to serve this site. See below for examples of how to make the regular expression work for your development environment.
Base domain name:
    The master domain name for the live site.
Homepage ID:
    The id of the homepage for this site. If this is left blank, then a homepage will be created when you save the site.

Seeing as we don’t have any other sites at the moment, we will leave the “Domain pattern” field blank. The “Name” and “Base domain name” fields are both mandatory. Enter “Main site” and “example.com” in these fields respectively (or adjust to suit your needs). In the “Homepage ID” field, enter the id of the homepage, which you took a note of above (‘1’ in our example). When you are ready, click “Save”.

If you click on the ‘Pages’ tab, you should now find a submenu with a link for “Main site”. As we add extra sites to our Radiant app, we should see them appear in this menu.

Lets add a couple more sites now, to make things interesting. This time, we will create a brand new site, rather than registering an existing one, so we will leave the “Homepage ID” field blank. Create two new sites using the following fields:

Name                Domain pattern        Base domain name
====                ==============        ================
Sub domain          sub\.example\.com     sub.example.com
Alternate domain    alternate\.com        alternate.com

If you now visit the ‘Pages’ tab, you should see the new sites in the submenu. When you click on them, a homepage with ‘draft’ status should have been created.
Ordering your sites

In the ‘Sites’ index page, you see an overview of all the sites in your Radiant app. The table summarizes various fields associated with each site. It also provides links to remove a site, and to reorder the sites. By clicking the ‘up’, ‘down’, ‘top’ and ‘bottom’ buttons, you can change the order that the sites appear in the ‘Pages’ submenu. Note that whichever site appears first in this list, will be the default view when you click the ‘Pages’ tab.
Running multiple sites in development mode

The multi-site extension chooses which site to serve by examining the <host> part of the URL.

<protocol>//<host>[:<port>]/<pathname>

To be able to test each of your sites in development mode, it will be necessary to set up aliases for your Radiant app, so that it can be accessed locally through more than one URL. Instructions on how to do this follow for Mongrel and Passenger development servers.
Using Mongrel

If you are using Mongrel in development, you will be accustomed to running script/server from your Radiant app, and accessing your site at http://localhost:3000/. In this case, ‘localhost’ is actually an alias for the IP address of your development machine: 127.0.0.1. This is defined in your local ‘hosts’ file. On unix machines (including Mac OS X and linux), this is usually located at /etc/hosts. (The wikipedia page describes where to find this file on Windows machines.) Open up the file in a text editor. Somewhere, it should include the line:

127.0.0.1 localhost

Copy this line, and paste it at the bottom of the file, on a new line of its own. Replace ‘localhost’ with the domain name of one of the sites in your Radiant app. Repeat this for each of the sites in your Radiant app.

When you are editing your hosts file, you should be careful to ensure that none of your local aliases could be used as a real domain name. If you have a live site at example.com, then you could create an alias on your local machine for example.local, as follows:

127.0.0.1 example.local

If you use this approach, you will have to update the “Domain pattern” for the site. Examples are given below.
Using Passenger

Some modification in apache :
sudo vim /etc/apache2/sites-enabled/000-default



<VirtualHost *:80>

     ServerName www.mainhost.com

     ServerAlias test-1.host.com test-2.host.com test-3.host.com

     DocumentRoot /github/radiant/public

     RailsEnv development #Use to run application in development mode


            Options FollowSymLinks

            AllowOverride None



Configure you host :

*linux
sudo vim /etc/hosts

127.0.0.1       test-1.host.com

127.0.0.1       test-2.host.com

127.0.0.1       test-3.host.com

Making “Domain patterns” that work for live and development domains

The example sites that we created above used a very simple regular expression to match the domain name. This should work for the live domains, but to make it work in development mode, we have to tweak the pattern so that it matches the local aliases we have created for our Radiant app. The following table suggests domain patterns for a handful of examples:

live domain       local alias           Domain pattern
===========       ===========           ==============
example.com       example.local         ^example\.(com|local)$
sub.example.com   sub.example.local     ^sub\.example\.(com|local)$
alternate.com     alternate.local       ^alternate\.(com|local)$

