By default, Radiant uses Rack::Cache to achieve it's goal of page caching for standard deployments.  Sometimes, however, you don't want any caching in Radiant so that you can see changes that you make to the site in "real time."  Obviously, doing active development on a Radiant site is one of these cases.  This is not limited to just standard Rails "development" mode (e.g. using the "development" environment in Rails).  Sometimes you are doing active development in a "production" (e.g. deployed app.).  Below we discuss the various ways to disable Radiant caching in any environment.

# How Radiant caches
Radiant uses standard Rack::Cache configuration to handle caching.  

Here is the (private) method in the Page class that controls the cache settings by setting the standard HTTP 'expires' and 'ETag' response headers:

    def set_cache_control
      if (request.head? || request.get?) && @page.cache? && live?
        expires_in self.class.cache_timeout, :public => true, :private => false
      else
        expires_in nil, :private => true, "no-cache" => true
        headers['ETag'] = ''
      end
    end

# Setting the cache timeout value
As you can see above, the Radiant Page class has a class level attribute named "cache_timeout" which is the amount of time that the standard HTTP "expires" response header is set for.  By default, this is 300 (seconds, which is 5 minutes).  You can change this value like this:

    config.after_initialize do
      Page.cache_timeout = 1.second
    end

in environment.rb or any of the environment-specific configuration files (e.g. development.rb, etc.).  This example reduces the cache timeout to 1 second.  You can set this value to 0.  Sometimes Google Chrome doesn't seem to like a 0 value, so you may want to consider the 1 second approach.

# Disabling caching for all pages
The other way to remove caching is to disable caching altogether for the Page class by overriding the cache? method.  You can do that like this:
    
    Page.class_eval do
      def cache?
        false
      end
    end

in environment.rb or any of the environment-specific configuration files (e.g. development.rb, etc.).  

# Disabling caching for only a certain class of pages
You are free to define custom Page types which may be assigned to Radiant pages in the admin. interface.  For example, you might define a NonCacheablePage class in an extension like this:

    class NonCacheablePage < Page
      def cache?
        false
      end
    end

and then assign certain Radiant pages to be this page type as needed.

# Disabling caching for Javascript and stylesheets
Because Javascript and stylesheets are managed by the "sheets" extension, they have separate settings for cache timeout.  As of this writing (Radiant 1.0.0 RC2), there are some issues around sheet management which force the following code to be used to disable caching for sheets.  Strictly speaking, this should not be necessary in future versions of Radiant.

    StylesheetPage.class_eval do
      def sheet?
        false
      end

      def cache?
        false
      end
    end

This code needs to be placed in an extension config file (e.g. xxx_extension.rb) to allow for the standard Radiant Page class to be loaded.