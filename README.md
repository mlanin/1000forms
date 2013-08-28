1000Forms
=========

"1000Forms" is a single file which you can copy and paste to any place of your project.

#### Features
 * It can be used in any sort of projects to build any sort of forms.
 * Any functionality can be extended. Validators, filters, elements - everything can be modified.
 * Markups of all elements and whole form can be modified as you like. Even by using a template
 * It already has a built-in engine to validate all the inputs
 * You can modify and filter all the values right in the form
 * You can create dynamic forms with array values



#### Simple example
```php
<?php
// Include main file
include_once 'class.forms.php';
 
// Initialise object
$form = new Form();
 
// Set name of our form
$form->setName('login_form');
 
// Add "Name" textfield
$form->addElement('textfield', 'login', array('label' => 'Name'));
// Add "Password" element
$form->addElement('password', 'password', array('label' => 'Password'));
 
// And add wubmit button
$form->addElement('submit', 'login', array('value' => 'Login'));
 
// Check if form was submitted and values are valid
if ($form->isValid()) {
    // Get values and print them
    print_r($form->getFormState());
}
 
// Print our form
print $form;
```