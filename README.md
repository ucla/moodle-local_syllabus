# Syllabus Plug-in

The Syllabus plug-in for Moodle is an easy-to-use feature which allows for the
integration of syllabi into the course . By displaying the syllabus document
clearly and separately from other uploaded media, students have an easier time
finding information that may be crucial to their success in the classroom.

## Download

Visit the [GitHub page for the Syllabus plug-in](https://github.com/ucla/moodle-local_ucla_syllabus) to download a package.

To clone the package into your organization's own repository from the command
line, clone the plug-in repository into your own Moodle install folder by 
doing the following at your Moodle root directory:

    $ git clone https://github.com/ucla/moodle-local_syllabus local/syllabus

## Installation

To configure the Syllabus plug-in with your own Moodle 2.5 instance, simply

1.  Place the plug-in files into your Moodle instance's "local/syllabus"
    directory.
2.  Log in to your Moodle site. Make sure you have administrative privileges.
3.  In the "Administration" block, follow "Site Administration" > "Notifications".
4.  Make sure that the Syllabus plug-in is flagged for installation, and press
    the "Upgrade Moodle database now" button at the bottom of the page.
5.  You should now have your own working version of the Syllabus plug-in.

## Configuration

All of the configuration work is done for you during the installation process.
The necessary tables are added to your database during Moodle upgrade process
in step 4 of the installation instructions.

## Features

### Uploading a syllabus

The Syllabus plug-in is automatically hooked in to the Navigation block of a
course. However, until a syllabus is uploaded for the course, students will not
be able to see a link to the syllabus. To upload a course syllabus, instructors
must:

1.  Navigate to the their course home page.
2.  Turn editing mode on.
3.  Click on the Syllabus link ("Syllabus (empty)") in the Navigation block 
    underneath the course heading.
4.  Select which type of syllabus they want to upload.
5.  Follow the instructions on the upload page, and press "Save changes"
    when finished.

### Viewing a syllabus

Once a syllabus has been uploaded to a course. Anyone with access to the course
page will see a link to the syllabus in the Navigation block. Depending on the
type of syllabus that is uploaded, some users may not be able to view the
syllabus (for example, if the syllabus is restricted and the user has only guest
access). 

Otherwise, following the Navigation link will take users to a page
displaying the information in the syllabus.

### Changing / deleting a syllabus

At any point after creating a syllabus, the instructor may alter it or remove
it from the site by:

1.  Navigating to their course home page.
2.  Clicking the link to the course syllabus in the Navigation block.
3.  Turning editing on.
4.  Selecting one of the options next to the syllabus file ("Edit", "Delete",
    "Restrict" / "Unrestrict").

## License

The syllabus plug-in adopts the same license that Moodle does (namely the GNU
General Public License v3). See the "LICENSE" file in your plug-in directory 
for details.

## Contact

Contributions of any form are welcome. GitHub pull requests are preferred. File
any bugs, improvements, or feature requests in the plug-in's [issue tracker](https://github.com/ucla/moodle-local_syllabus/issues).

## Known Issues

As with any open-source project, the Syllabus plug-in for Moodle is always
evolving. While one would hope that things would only improve, the addition of 
new features always opens up opportunities for new bugs to find their way into 
the code. Existing issues and desired improvements have been flagged with
a "TODO" tag in the comments.

## Credits

The Syllabus Project is an open-source plug-in for the Moodle API originally
created for use by [CCLE](https://ccle.ucla.edu/), UCLA's primary online learning environment.

Copyright 2013 UC Regents.

## Change Log

Initial Release.
