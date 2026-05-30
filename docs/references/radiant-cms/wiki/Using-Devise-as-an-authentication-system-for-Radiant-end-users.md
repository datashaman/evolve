If you want to require your end users to authenticate in order to see Radiant content, you need to put in authentication for them.  In theory, you could use the same user and authentication framework (LoginSystem) that Radiant provides for administrative users, but that could prove confusing over time.  Another approach is to divorce the Radiant end user authentication system from that for the Radiant users.  One successful approach to this is described below.

Radiant 0.9.1 (embedded Rails 2.3.8)<br/>
Devise 1.0.8

1) Because Radiant already has a User model, you have to set up Devise to use a different model for authentication.  So, you should choose a model name, and use that when you install Devise per the directions.  For the purposes of the rest of this explanation, let's assume that the model name is CustomUser.

2) Do all of your setup for Devise for CustomUser, including setting up the "devise_for" route (devise_for :custom_user, etc.).  I set up my Devise route in my custom extension's routes.rb file.

3) To protect all of the end-user content, you need to call the Devise authenticate method somewhere as a before_filter on the SiteController.  I did the following:

   a) created a module named ContentManagement which gets included into SiteController (see (c) below)

   b) inside ContentManagement, add the following method:
```ruby
   #Don't authenticate CSS or JS requests, as these will do redirects
   def radiant_page_request?
     ! (params[:url] && params[:url].is_a?(Array) && (params[:url].include?('css') || params[:url].include?('js')))
   end
```
   c) In the custom extension file, do:
```ruby
   SiteController.class_eval do
     include ContentManagement
     prepend_before_filter {|controller| controller.instance_eval {authenticate_custom_user! if radiant_page_request?}}
   end
```

4) Once you do this, Devise will be set up to be used to authenticate against your end-user content.  But it won't work, because both Devise and Radiant inject a method named "authenticate" directly into the ApplicationController via module inclusion (luckily, they have different method signatures, or I would have had a tough time figuring that out).

I attempted to force the segregation of these two authenticate methods using nothing but fancy metaprogramming to try and change inheritance hierarchies and what-have-you.  I don't think that is possible, actually.  Ultimately, I decided that the solution with the least customization (and thus, easiest to manage over time), would be to statically bypass the ApplicationController provided by Radiant in Devise.  So...

5) Create a new controller named DeviseController:

```ruby
   class DeviseController < ActionController::Base
     layout 'devise'
   end
```

I put mine in my custom extension's app/controllers directory, but you can put it anywhere as long as it is loaded before any of the Devise stuff. 

Also, notice that I built a custom layout for Devise (devise.html.haml) that looks like my Radiant-managed layout for end-user content. 

Yes, that means that changes to the layout within Radiant must be repeated in this layout file.  However, I couldn't figure out a good way to share the layouts (even using file_based_layout, I would have had significant duplication to deal with - I decided to go the simplest route).  You can't use shared_layout to share an existing Radiant layout since the Devise views are not rendered via SiteController.

6) Put a copy of the Devise gem into RAILS_ROOT (or RADIANT_ROOT)/vendor/gems

7) In vendor/gems/devise/app/controllers, change each of the five 5 Devise controller's class definitions so that they descend from DeviseController, instead of ApplicationController, like so:

```ruby
   class ConfirmationsController < DeviseController
```

Note that each of the 5 controllers does:

```ruby
   include Devise::Controllers::InternalHelpers
```

and this is where the conflicting "authenticate" method comes from.  But now, because there's no more ApplicationController in the hierarchy, there is no conflict.

CAVEAT:
It is very important that the route file that pulls in the Devise route executes before you attempt to use any of the Devise "custom_user" specific helpers.  The route configuration is what creates all of the Devise helpers that are then included in various places.  If you cause any helper files to be included before the Devise route is processed, none of the "custom_user" helper methods will be available.  This may pose a problem, as you might want to include some of the Devise helpers elsewhere.  One way to get around this is to use autoload on those modules/classes that need to include the Devise helpers.  Since autoload doesn't load the module/class until it is used (which presumably would be _after_ the loading of the Devise route), it will work.

It would be interesting to see if it made sense to use Devise for all Radiant authentication (e.g. both end users and Radiant users), as Devise has authentication for different scopes baked in from the start.