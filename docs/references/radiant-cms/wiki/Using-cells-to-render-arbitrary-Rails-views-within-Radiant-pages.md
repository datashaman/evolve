By default, Radiant provides its own rendering system using layouts, snippets, pages, and Radius tags.

Using plugins such as shared_layouts, one can take advantage of Radiant layouts in their standard (non-Radiant managed) Rails views.

And of course, if you want to render content that is not managed by Radiant (e.g. via a controller that is _not_ a descendant of SiteController), you can do that.

But what happens when you want to embed standard Rails views into a Radiant generated page?  You want the Radiant page generation framework, and all of the user-side goodness that provides, but you don't want to be limited to the standard set of Radius tags (or those made available through various plugins)?

One approach is to write your own plugin to replicate all of the view processing that you need to do in Radius tags.  But why should you recreate all of the view helper stuff that you get automatically with Rails?

One way to "mix and match" Radiant and Rails content is to use the [["cells"|http://github.com/apotonick/cells]] gem which provides reusable view components outside of the context of a controller.

Below is one example of how I used cells to embed a regular Rails-rendered form in a Radiant page, because I found the Radiant extension too limiting.  What I like about it is I can embed arbitrarily complex forms without having to leave the Radiant rendering system.  

At a very high - level:

* Install [[cells 3.3.4|http://github.com/apotonick/cells]] (anything higher is Rails 3 specific)

* Added these commands so that cells can be processed from within Radiant.  I added these to an initializer file in config/initializers named cell_setup.rb.  (NOTES: I run Radiant out of /vendor/radiant, not as a gem, so I am doing this in RAILS_ROOT/config/initializers, not RADIANT_ROOT/config/initializers.  I haven't tested it in the latter scenario.  Also, I am autoloading the cell classes in order to not interfere with other aspects of my system.)  

```ruby
  ##Cell configuration
  CELL_PATH = "#{RAILS_ROOT}/path_to_your_cells_directory"
  CELL_FILES = Dir.glob("#{CELL_PATH}/*_cell.rb").map {|f| File.basename(f)}

  #Modify view paths for ::Cell::Base to include local cell view directories
  ::Cell::Base.view_paths.unshift CELL_PATH

  #Add CELL_PATH to Rails $LOAD_PATH
  $LOAD_PATH.unshift CELL_PATH

  #Autoload cells - we have to do this here, since cells are defined in the top-level namespace.
  #If there aren't any conflicts with other aspects of the system, you could do a normal require here.
  CELL_FILES.each {|f| autoload f.split('.')[0].classify.to_sym, f}
```
   
* Created cells to render my form.  Here's an example:

  app/cells/question_instances_cell.rb:
```ruby
  class QuestionInstancesCell < ::Cell::Base
    def new
      @question_type = QuestionType.find(@opts['question_type_id'])
      if @question_type.question_type =~ /FAQ/
        @question_instance = QuestionInstance.new()
        view = :edit_faq
      else
        @question_instance = QuestionInstance.new(:question => @question_type.questions.first)
        view = :edit
      end
   
      render :view => view
    end
  end
```

* Here are the cell view files in /app/cells/question_instances:
```bash

  -rw-r--r--@ 1 weyus  staff  568 Aug 30 16:46 edit.html.haml
  -rw-r--r--@ 1 weyus  staff  658 Sep  6 13:21 edit_faq.html.haml
```

* Here is the standard Haml template for the "edit" cell view:
/app/cells/question_instances/edit.html.haml:
```haml
  - type = @question_type.question_type
  - form_for [:admin, @question_instance] do |f|
    = f.error_messages
    %p
      = type
      %br/
      = f.select :question_id, @question_type.questions.map {|q| [q.question, q.id]}
    %p
      = f.label :question_text, "Edit the question to best fit your selling situation"
      %br/
      = f.text_area :question_text, :value => @question_type.questions.first.question, :class => 'short'
    %p
      = f.label :new_form, "Would you like to build another #{type} question?"
      = check_box_tag :new_form, 1, false
    %p
      = submit_tag 'Submit'
```

* Created a custom tag to allow for a cell to be rendered inside of a Radiant template:
```ruby
  tag "cell" do |tag|
     if tag.attr['name'] && tag.attr['view']
       name, view = tag.attr.delete('name'), tag.attr.delete('view')
       tag.locals.page.response.template.controller.render_cell(name, view, tag.attr)
     else
       raise TagError.new("`cell' tag must contain `name' and `view' attributes")
     end
   end
```

* Call the tag from my Radiant template:
```html
  <r:cell name="question_instances" view="new" question_type_id="9"/>
```