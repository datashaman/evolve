When you have two or more existing extensions and you decide that you should merge them for some reason, here are the steps that you need to take:

Assuming that you are merging extension B into extension A:

1. Move all of the contents of B/app into A/app

2. Place the contents from B/config/routes.rb into A/config/routes.rb

3. Move all of the contents of B/db/migrate into A/db/migrate. In addition, assuming that the migrations in B have already been run on your database, then you must update the schema_migrations table to make it look like those migrations belong to A instead of B. The way that Radiant handles extension specific migrations is to prepend the extension name before the migration timestamp (or version #). So, in your schema_migrations table, you will see entries for B_. These need to be updated to reflect entries such as A_ so that they will not be re-run the next time you run migrations for the A extension.

4. Place the contents of B_extension.rb into A_extension.rb

5. Update A/lib/tasks/A_extension_tasks.rb with any custom tasks defined in B/lib/tasks/B_extension_tasks.rb

6. Place the contents of B/features and B/spec into A/features and A/spec respectively.

7. Remove B.
