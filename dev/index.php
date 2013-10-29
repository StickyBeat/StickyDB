<?php?>


<pre>
<?

set_include_path( get_include_path() . PATH_SEPARATOR . '../' );

include'stickydb.php';

StickyDB::$executeQueries = false;
StickyDB::$echoQueries = true;

Thing::readAll();

$code = '

$obj = new thing();
$data = $obj->get();

Thing::readAllWithName( "bitch", true, 100 );

$dir = new DOMDocument();

exec("dir");

$lala = "mkdir()";

$lala();

function bad(){
}

class doom{
}

eval("dir");

`ls`

';

function checkCode( $code ) {

      $errors = array();
      $tokens = token_get_all('<?'.'php '.$code.' ?'.'>');    
      $vcall = '';
      
      foreach ($tokens as $token) {
      
        if (is_array($token)){
          
          list( $id, $value ) = $token;
          
          switch ($id)
          {
          
            case T_VARIABLE:
            	$vcall .= 'v';
            	break;
            
            case T_NEW: 
            case T_WHITESPACE: 
            case T_OPEN_TAG: 
            case T_CLOSE_TAG: 
            case T_OBJECT_OPERATOR:
            case T_DOUBLE_COLON:
            case T_CONSTANT_ENCAPSED_STRING:
            case T_LNUMBER:
            case T_DNUMBER:
            case T_ARRAY: 
            	break;
            
            default:
	            $errors[] = 'Illegal ' . token_name( $id ).': '.$value;
            	break;
            	
            case T_STRING:
            	
            	$vcall .= 's';
            	
            	switch( strtolower( $value ) ){
            		
            		case 'true':
            		case 'false':
            			break;
            			
            		default:
            			
		            	if( function_exists( $value ) ){
			            	$errors[] = 'Illegal function usage: '.$value;
		            	}
		            	else if( class_exists( $value, false ) ){
			            	$errors[] = 'Illegal class usage: '.$value;
		            	}
		            	else{
			            	#$errors[] = 'Illegal: '.$value;
		            	}
	            		break;
            	}
	            break;
        	}
        }     
        else 
          $vcall .= $token;
      }
      
      if (stristr($vcall, 'v(') != ''){
      
      	$errors[] = 'Illegal dynamic function call';
      	
      }
      
      return $errors;
}

/*
$errors = checkCode( $code );

if( $errors ){
	var_export( $errors );
}
else{
	eval( $code );
}

exit;
*/

$all = Test::readAll( 'age > ? AND hair != ?', 20, '"shit"-brown', 'age DESC', 10, 20 );

$one = Other::readOne( 'eyes = ? OR eyes = ?', 'green', 'yellow' );

$example = new Example();

$example->readParentExampleType();
$example->hasExampleTypeWhereHidden();
$example->hasExampleType( 'hidden = ?', 'yes' );

$example->readChapters();
$example->readChaptersWithImage();
$example->readOneChildChapterByNr( 5 );

$example->readLinkedCategories();
$example->readChildren('fungus');
$example->readParent('book');
$example->deleteChildren('book');
$example->countLinked('language');
$example->readExampleTypes('public=?', 1, 'created DESC', 5 );

$example->readOneSlipperWithSmell('awful');
$example->readChildSlipperWithSmell('awful');
$example->readSlippersWithSmell('awful');
$example->readChildrenWithSmell('fungus','awful');
$example->readChildWithSmell('fungus','peachy','age DESC');
$example->readChild('fungus','scent != ?','bad','age DESC');






Test::delete( 'name LIKE ?', '%"cool kid"%' );

Test::count( 'type = ?', 'bad' );

Test::has( 'age > ?', 100 );

Test::readOneWithId( 334 );

Test::readWithLastName( "Al'Jafar" );

Test::readAllWithBrainTypeOrAgeOrHairColor( 'stupid', 13, 'blue', 'created' );

Test::hasAnyWithName( "Sven" );

Test::deleteWithId( 12345 );
Test::deleteOneWithId( 12345 );

Test::countWithType( 'good' );

/*
$lala = new Thing();
$lala->message = "What's the thing?";
$lala->hello = 1;

$lala->write();
*/


$heading = "Jury Duty!";
$text = "You have been selected for Jury Duty.\n Is this a valid shot?\nTarget: ".'NAME';

$message = new message();
$message->receiver = $firstjury;
$message->heading = $heading;
$message->message = $text;
$message->timestamp = time();
$message->parameter = $mission->id;
$message->write();

#Test::delete( 'name LIKE ?', '%"cool kid"%' );
#Test::read( 'name LIKE ?', '%"cool kid"%' );





#print_r( $all );


?>
</pre>
