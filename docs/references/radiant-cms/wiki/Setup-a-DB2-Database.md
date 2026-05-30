Setting up Radiant CMS with DB2 is straightforward. Simply follow the few steps below.

### Prerequisites

Before continuing with the setup procedure described below, ensure that you have DB2 installed. You can obtain a free, production ready copy at the [DB2 Express-C site](http://db2express.com/download/?S_TACT=ACRADIANTWIKI). During the DB2 setup on Linux, you need to select a custom installation in order to include the Application Development Development files and libraries. These are needed to compile the DB2 Ruby driver provided and supported by IBM.

### Install the DB2 Ruby driver and Rails adapter

On *nix based systems, you can install both the Ruby driver and Rails adapter for DB2 with the following commands:

<pre>$ sudo -s
$ export IBM_DB_INCLUDE=/home/db2inst1/sqllib/include
$ export IBM_DB_LIB=/home/db2inst1/sqllib/lib
$ source /home/db2inst1/sqllib/db2profile
$ gem install ibm_db</pre>

On Windows, you'll only need the last line, given that the gem for Windows ships with precompiled binaries.

### Create databases

You can create development, test, and production databases as follows:

<pre>$ su - db2inst1 -c 'db2 create db BLOG_DEV'
$ su - db2inst1 -c 'db2 create db BLOG_TST'
$ su - db2inst1 -c 'db2 create db BLOG'
</pre>

The database names must be limited to 8 characters. For performance reasons, it is advised to enable the [Statement Concentrator feature](http://publib.boulder.ibm.com/infocenter/db2luw/v9r7/index.jsp?topic=/com.ibm.db2.luw.wn.doc/doc/c0054005.html) in production if you are running DB2 9.7 or greater:

<pre>$ su - db2inst1 -c 'db2 update db config for BLOG using stmt_conc literals'
</pre>

### Configure database.yml

The config/database.yml file should be along the lines of the following configuration:

<pre>development:
  adapter:     ibm_db
  username:    &lt;username e.g., db2inst1&gt;
  password:    &lt;password&gt;
  database:    blog_dev
  schema:      &lt;schema e.g., db2inst1&gt;
  host:        &lt;hostname or IP, e.g., localhost&gt;
  port:        &lt;port e.g., 50000&gt;

test:
  adapter:     ibm_db
  username:    &lt;username e.g., db2inst1&gt;
  password:    &lt;password&gt;
  database:    blog_tst
  schema:      &lt;schema e.g., db2inst1&gt;
  host:        &lt;hostname or IP, e.g., localhost&gt;
  port:        &lt;port e.g., 50000&gt;

production:
  adapter:     ibm_db
  username:    &lt;username e.g., db2inst1&gt;
  password:    &lt;password&gt;
  database:    blog
  schema:      &lt;schema e.g., db2inst1&gt;      
  host:        &lt;hostname or IP, e.g., localhost&gt;
  port:        &lt;port e.g., 50000&gt;</pre>

Schema, host, and port are optional values. Schema defaults to the given username, and a missing hostname and port combo indicates to the adapter that a local connection to DB2 should be established.