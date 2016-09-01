<?php


spl_autoload_register( 'stickydb_autoload');

function stickydb_autoload( $classname )
{
	if( function_exists('get_called_class') )
	{
		eval( 'class '.$classname.' extends stickydb{}' );
	}
	else
	{
		global $stickydb_generated_class;

		if( !isset( $stickydb_generated_class ) )
		{
			$phpData = file_get_contents( __FILE__ );

			$phpData = strstr( $phpData, '// '.'START OF CLASS DECLARATION' );
			$phpData = substr( $phpData, 0, strrpos( $phpData, '// END OF CLASS DECLARATION' ) );

			$phpGenerated = '';

			$generateMethods = array(
				'read',
				'readOne',
				'readAll',
				'deleteOne',
				'deleteAll',
				'count',
				'has',
			);

			foreach( $generateMethods as $method )
			{
				$versions = array( $method );

				$underscoreMethod = implode('_', ( stickydb::split( $method ) ) );

				if( $underscoreMethod != $method )
					$versions[] = $underscoreMethod;

				foreach( $versions as $version )
					$phpGenerated .= 'public '.$type.' function '.$version.'(){ $a = func_get_args(); return self::__callStatic( "'.$version.'", $a ); }';
			}

			$phpData = str_replace( '// INSERT GENERATED METHODS', $phpGenerated, $phpData );

			$stickydb_generated_class = $phpData;
		}

		$phpData = str_replace( 'class StickyDB', 'class '.$classname.' extends StickyDB', $stickydb_generated_class );

		eval( $phpData );
	}
}

// START OF CLASS DECLARATION

class StickyDB
{
	private $modifiedFields = array();
	private $fields = array();
	private $isNew = true;

	private static $connection = null;

	public static $executeQueries = true;
	public static $echoQueries = false;

	public function __construct( $input = null, $new = true )
	{
		$this->isNew = $new;
		$this->modifiedFields = array();

		if( is_array( $input ) )
		{
			if( $new ){
				$this->set( $input );
			}
			else{
				$this->fields = $input;
				$this->isNew = false;
				$this->modifiedFields = array();
			}
		}
		else if( is_numeric( $input ) )
		{
			$class = get_class( $this );

			$result = self::query('SELECT * FROM ' . self::tick( self::makeTableName( $class ) ).' WHERE '.self::tick( self::makePrimaryKeyName( $class ) ).' = ?', $input );

			$data = self::fetch( $result );

			if( is_array( $data ) ){

				#$this->set( $data );

				$this->fields = $data;

				$this->isNew = false;
				$this->modifiedFields = array();
			}
		}

	}

	public static function create( $input = null, $new = true )
	{
		$class = self::getClass();

		return new $class( $input, $new );
	}

	public static function getClass(){
		if( function_exists('get_called_class') )
			return get_called_class();
		else
			return get_class();
	}

	public function get( $name=null, $default=null )
	{
		if( is_null( $name ) )
		{
			return $this->fields;
		}
		else if( is_array( $name ) ){

			$values = array();

			foreach( $name as $field ){

				$values[ $field ] = $this->get( $field );

			}

			return $values;

		}
		else{

			$name = self::makeFieldName( $name );

			if( isset( $this->fields[ $name ] ) )
			{
				return $this->fields[ $name ];
			}
			else
			{
				return $default;
			}
		}
	}

	public function set( $name, $value = null )
	{
		$modified = false;

		if( is_array( $name ) ){

			foreach( $name as $n => $v ){
				if( $this->set( $n, $v ) ){
					$modified = true;
				}
			}
		}
		else{

			$name = self::makeFieldName( $name );

			if( !in_array( $name, $this->modifiedFields ) ){

				$previousValue = $this->get( $name );

				if( $previousValue !== $value ){

					$this->modifiedFields[] = $name;

					$modified = true;
				}
			}

			$this->fields[ $name ] = $value;

			#$class = get_class( $this );

			#echo $class.'->'.$name.' = '.$value."\n";
		}

		return $modified;
	}


	public function reload(){

		if( $this->isOld() ){

			$q = 'SELECT * FROM '.self::tick( self::makeTableName( $class ) ).' WHERE ' . self::tick( $this->getPrimaryKeyName() ) . ' = ' . $this->getPrimaryKey();

			$result = self::query( $q );

			$data = self::fetch( $result );

			if( is_array( $data ) ){

				$this->fields = $data;
				$this->isNew = false;
				$this->modifiedFields = array();
			}
		}

	}


	function write()
	{
		if( empty( $this->modifiedFields ) && !$this->isNew() ){
			return false;
		}

		$class = get_class( $this );

		if( $this->isNew() ){

			$q = 'INSERT INTO '.self::tick( self::makeTableName( $class ) ).' ( ';

			$first = true;
			foreach( $this->modifiedFields as $field ){

				if( $first ){
					$first = false;
				}
				else{
					$q .= ', ';
				}

				$q .= self::tick( self::makeFieldName( $field ) );
			}

			$q .= ' ) VALUES ( ';

			$first = true;
			foreach( $this->modifiedFields as $field ){
				if( $first ){
					$first = false;
				}
				else{
					$q .= ', ';
				}

				$q .= self::quoteValue( $this->get( $field ) );
			}

			$q .= ' )';

			self::query( $q );

			$this->set( self::makePrimaryKeyName( $class ), self::insertId() );
		}
		else{

			$q = 'UPDATE '.self::tick( self::makeTableName( $class ) ).' SET ';

			$first = true;
			foreach( $this->modifiedFields as $field ){

				if( $first ){
					$first = false;
				}
				else{
					$q .= ', ';
				}

				$q .= self::quote( self::tick( self::makeFieldName( $field ) ) . ' = ?', array( $this->get( $field ) ) );
			}

	    	$arguments = func_get_args();

			if( empty( $arguments ) ){
				$q .= ' WHERE ' . self::quote( self::tick( self::makePrimaryKeyName( $class ) ) . ' = ?', array( $this->getPrimaryKey() ) );
			}
			else{

				$str = array_shift( $arguments );

				$q .= self::quote( ' WHERE '.$str, $arguments );
			}

	    	#echo '/* '.$q." */\n";

			self::query( $q );
		}

		$this->modifiedFields = array();

		$this->isNew = false;

		return true;
	}

	public function delete()
	{
		if( isset( $this ) ){
			return self::query( 'DELETE FROM '.self::tick( self::makeTableName( get_class( $this ) ) ).' WHERE ' . self::tick( $this->getPrimaryKeyName() ) .' = ?', $this->getPrimaryKey() );
		}
	}

	public function getPrimaryKey()
	{
		return $this->get( self::getPrimaryKeyName() );
	}

	public static function getPrimaryKeyName(){
		return self::makePrimaryKeyName( self::getClass() );
	}

	public static function getForeignKeyName(){
		return self::makeForeignKeyName( self::getClass() );
	}

	public function __get( $name )
	{
		return $this->get( $name );
	}

	public function __set( $name, $value )
	{
		$this->set( $name, $value );
	}

	public function getIniName()
	{
		return 'default';
	}

	public function __call( $name, $arguments )
	{
		$class = get_class( $this );

		#echo $class.'->'.$name.'( '.implode(', ',$arguments).' )'."\n";

		$nameParts = self::split( $name );

		$action = array_shift( $nameParts );

		if( in_array( $action, array('read','delete','count','has') ) )
		{
			if( in_array( current( $nameParts ), array('all','one','any') ) )
				$amount = array_shift( $nameParts );
			else
				$amount = false;

			if( in_array( current( $nameParts ), array('parent','child','linked') ) )
				$relation = array_shift( $nameParts );
			else
				$relation = false;

			$typeNameParts = array();

			while( !in_array( current( $nameParts ), array( 'by','where','with', false ) ) )
				$typeNameParts[] = array_shift( $nameParts );

			if( empty( $typeNameParts ) )
			{
				if( $relation )
					$typeNameParts = array( array_shift( $arguments ) );
			}
			else
			{
				$lastNamePart = end( $typeNameParts );

				if( substr( $lastNamePart, strlen( $lastNamePart )-3 ) == 'ies' )
				{
					$lastNamePart = substr( $lastNamePart, 0, strlen( $lastNamePart )-3 ).'y';
					if( !$relation ) $relation = 'children';
				}
				else if( substr( $lastNamePart, strlen( $lastNamePart )-1 ) == 's' )
				{
					$lastNamePart = substr( $lastNamePart, 0, strlen( $lastNamePart )-1 );
					if( !$relation ) $relation = 'children';
				}
				else
				{
					if( !$relation ) $relation = 'parent';
				}

				if( $lastNamePart == 'children' && count( $arguments ) )
				{
					$typeNameParts = array( array_shift( $arguments ) );
					$relation = 'children';
				}
				else
					$typeNameParts[ count( $typeNameParts )-1 ] = $lastNamePart;
			}

			if( !$amount )
			{
				if( $relation == 'parent' )
					$amount = 'one';
				else if( $relation == 'child' )
					$amount = 'one';
				else if( $action == 'has' )
					$amount = 'one';
				else if( $relation )
					$amount = 'all';
			}

			$otherClass = self::makeClassName( $typeNameParts );

			switch( $relation )
			{
			    case 'parent':

			    	$forwardNameParts = array( $action, $amount );
			    	$forwardNameParts[] = 'stickydbrelation';
			    	$forwardNameParts = array_merge( $forwardNameParts, $nameParts );

			    	$forwardArguments = array();
			        $forwardArguments[] = self::quote( self::tick( self::makePrimaryKeyName( $typeNameParts ) ) . ' = ?', array( $this->get( self::makeForeignKeyName( $typeNameParts ) ) ) );
			    	$forwardArguments = array_merge( $forwardArguments, $arguments );

			        return call_user_func( array( $otherClass, '__callStatic' ), implode('_',$forwardNameParts), $forwardArguments );

			    case 'child':
			    case 'children':

			    	$forwardNameParts = array( $action, $amount );
			    	$forwardNameParts[] = 'stickydbrelation';
			    	$forwardNameParts = array_merge( $forwardNameParts, $nameParts );

			    	$forwardArguments = array();
			        $forwardArguments[] = self::quote( self::tick( self::makeForeignKeyName( $class ) ).' = ?', array( $this->get( self::makePrimaryKeyName( $typeNameParts ) ) ) );
			    	$forwardArguments = array_merge( $forwardArguments, $arguments );

			        return call_user_func( array( $otherClass, '__callStatic' ), implode('_',$forwardNameParts), $forwardArguments );

				case 'linked':
					break;

				default:

			    	$forwardNameParts = $nameParts;

			    	if( $amount )
				    	array_unshift( $forwardNameParts, $amount );

			    	array_unshift( $forwardNameParts, $action );

			        return call_user_func( array( $class, '__callStatic' ), implode('_',$forwardNameParts), $arguments );
			}
		}
		else if( $action == 'is' )
		{
			$isWhat = array_shift( $nameParts );

			switch( $isWhat ){

				case 'new':
					return $this->isNew;

				case 'old':
					return !$this->isNew;

				case 'modified':

					if( empty( $arguments ) ){
						return !empty( $this->modifiedFields );
					}
					else{

						$fields = current( $arguments );

						if( !is_array( $fields ) ){
							$fields = array( $fields );
						}

						foreach( $fields as $field ){

							$field = self::makeFieldName( $field );

							if( in_array( $field, $this->modifiedFields ) ){
								return true;
							}
						}

						return false;
					}

				default:
					return false;
			}
		}
		else{

			throw new BadMethodCallException();
		}
	}

	public static function __callStatic( $name, $arguments )
	{
		$class = self::getClass();

		#echo $class.'::'.$name.'( '.implode(', ',$arguments).' )'."\n";

		$nameParts = self::split( $name );

		$action = array_shift( $nameParts );

		if( in_array( $action, array('read','delete','count','has') ) )
		{
			if( in_array( current( $nameParts ), array('all','one','any') ) )
				$single = array_shift( $nameParts ) != 'all';
			else
				$single = false;

			if( current( $nameParts ) == 'stickydbrelation' )
			{
				array_shift( $nameParts );
				$relationWhere = array_shift( $arguments );
			}
			else
				$relationWhere = false;

			if( empty( $nameParts ) )
			{
				$where = array_shift( $arguments );

				#var_export( $arguments );

				/*
				if( is_array( current( $arguments ) ) )
					$whereValues = array_shift( $arguments );
				else
				*/

				#	$whereValues = &$arguments;
				#$where = self::quote( $where, &$whereValues );

				$where = self::quoteFrom( $where, $arguments );
			}
			else
			{
				$whereParts = array();
				$fieldParts = array();
				$type = false;

				while( true )
				{
					$part = array_shift( $nameParts );

					if( !$part or in_array( $part, array('with','by','where','and','or','is') ) )
					{
						if( count( $fieldParts ) )
						{
							$value = array_shift( $arguments );

							$whereParts[] = array
							(
								'type' => $type,
								'field' => $fieldParts,
								'value' => $value
							);

							$fieldParts = array();
						}

						if( !$part )
							break;

						if( $part == 'and' || $part == 'or' )
							$type = $part;
					}
					else
						$fieldParts[] = $part;
				}

				$where = '';

				while( list( $index, $part ) = each( $whereParts ) )
				{
					if( $index > 0 )
						$where .= ' '.strtoupper( $part['type'] ).' ';

					$where .= self::quote( self::tick( self::makeFieldName( $part['field'] ) ) . ' = ?', array( $part['value'] ) );
				}
			}

			if( $relationWhere )
			{
				if( $where )
					$where = $relationWhere . ' AND ( '.$where.' )';
				else
					$where = $relationWhere;
			}

			$sort = array_shift( $arguments );

			if( $single )
			{
			    $limit = 1;
			    $offset = 0;
			}
			else
			{
				$limit = array_shift( $arguments );
				$offset = array_shift( $arguments );
			}

			switch( $action )
			{
				case'read':
					$query = 'SELECT * FROM';
					break;

				case'delete':
					$query = 'DELETE FROM';
					break;

				case'count':
				case'has':
					$query = 'SELECT COUNT(*) AS count FROM';
					break;
			}

			$query .= ' '.self::tick( self::makeTableName( $class ) );

			if( $where )
			    $query .= ' WHERE '.$where;

			if( $sort ){

				$sortParts = explode(',',$sort);

				foreach( $sortParts as $i => $sortPart ){

					$subParts = explode(' ',trim( $sortPart) );

					if( !preg_match( '/[\(\)]/', $subParts[ 0 ] ) ){
						$subParts[ 0 ] = self::makeFieldName( $subParts[ 0 ] );
					}

					$sortParts[ $i ] = implode( ' ', $subParts );
				}

				$sort = implode(',',$sortParts);

			    $query .= ' ORDER BY ' . $sort;
			}

			if( $limit )
			{
			    $query .= ' LIMIT ';
			    if( $offset )
			        $query .= $offset.', ';

			    $query .= (int)$limit;
			}

			/*
			if( $action == 'has' )
				echo '// ' . $query;
			*/

			$result = self::query( $query );

			switch( $action )
			{
				case 'read':

					if( $single )
					{
						$data = self::fetch( $result );

						if( $data ){
							return self::create( $data, false );
						}
						else{
							return null;
						}
					}
					else
					{
						$objects = array();

						while( $data = self::fetch( $result ) )
						{
							$objects[] = self::create( $data, false );
						}

						return $objects;
					}

				case 'has':

					$data = self::fetch( $result );

					if( $data ){
						return !empty( $data['count'] );
					}
					else{
						return false;
					}

				case 'count':

					$data = self::fetch( $result );

					if( $data ){
						return $data['count'];
					}
					else{
						return 0;
					}

				default:

					return $result;
			}
		}
	}

	private static $nameCache = array();

	public static function makeName( $parts, $type )
	{
		$class_prefix = implode('_',self::split( self::getClass() ) );

		$cacheKey = $class_prefix.'.';

		if( is_array( $parts ) ){
			$cacheKey .= implode('_',$parts);
		}
		else{
			$cacheKey .= $parts;
		}

		$cacheKey .= '.'.$type;

		if( isset( self::$nameCache[ $cacheKey ] ) ){
			return self::$nameCache[ $cacheKey ];
		}

		$inis = array(
			'static',
			'delimiter',
			'case',
			'prefix',
			'suffix',
			'transform',
			);

		foreach( $inis as $key )
		{
			$value = self::ini( $class_prefix . '.' . $type . '.' . $key );

			if( $value === null ){
				$value = self::ini( $type.'.'.$key );
			}

			if( $value === null ){
				$value = self::ini( $key );
			}

			#echo $class_prefix . '.' . $type . '.' . $key.' = '.$value."\n";

			$$key = $value;
		}

		if( $static )
			return $static;

		if( !$parts ){
			$parts = self::getClass();
		}

		if( is_object( $parts ) )
			$parts = get_class( $parts );

		if( is_string( $parts ) )
			$parts = self::split( $parts );

		switch( $transform ){

			case'singularize':

				$lastPart = array_pop( $parts );

				if( strrpos( $lastPart, 'ies' ) === strlen( $lastPart ) - 3 ){
					$lastPart = substr( $lastPart, 0, strlen( $lastPart ) - 3 ) . 'y';
				}
				else if( strrpos( $lastPart, 's' ) === strlen( $lastPart ) - 1 ){
					$lastPart = substr( $lastPart, 0, strlen( $lastPart ) - 1 );
				}

				array_push( $parts, $lastPart );

				break;

			case'pluralize':

				$lastPart = array_pop( $parts );

				if( strrpos( $lastPart, 'y' ) == strlen( $lastPart ) - 1 ){
					$lastPart = substr( $lastPart, 0, strlen( $lastPart ) - 1 ) . 'ies';
				}
				else{
					$lastPart .= 's';
				}

				array_push( $parts, $lastPart );

				break;
		}

		$name = $prefix;

		foreach( $parts as $index => $part )
		{
			if( $index )
				$name .= $delimiter;

			switch( $case )
			{
				case'lowercase':
					break;

				case'uppercase':
					$part = strtoupper( $part );
					break;

				case'pascal':
					$part = ucfirst( $part );
					break;

				case'camel':
					if( $index )
						$part = ucfirst( $part );
					break;
			}

			$name .= $part;
		}

		$name .= $suffix;

		#echo $cacheKey.' -> '.$name."\n\n";

		self::$nameCache[ $cacheKey ] = $name;

		return $name;
	}

	public static function tick( $str )
	{
		return '`' . $str . '`';
	}

	public static function makeClassName( $name = null )
	{
		return self::makeName( $name, 'class' );
	}

	public static function makePrimaryKeyName( $name = null )
	{
		return self::makeName( $name, 'primaryKey' );
	}

	private static function makeForeignKeyName( $name = null )
	{
		return self::makeName( $name, 'foreignKey' );
	}

	public static function makeTableName( $name = null )
	{
		return self::makeName( $name, 'table' );
	}

	public static function makeFieldName( $name = null )
	{
		return self::makeName( $name, 'field' );
	}

	public static function connect()
	{
		if( !self::$connection ){

		    switch( self::ini('type') ){

		    	default:
		    	case'mysql':
				    
				    $connection = mysql_connect( self::ini('host','localhost'), self::ini('user'), self::ini('pass') );
				    mysql_select_db( self::ini('name'), $connection );
				    mysql_set_charset( self::ini('charset','utf8'), $connection );

				    break;

		    	case'mysqli':
				    
				    $connection = mysqli_connect( self::ini('host','localhost'), self::ini('user'), self::ini('pass'), self::ini('name') );

				    break;
		    }

		    self::$connection = $connection;
		}
		
	    return self::$connection;
	}

	public static function query( $query )
	{
	    $arguments = func_get_args();

	    if( count( $arguments ) )
	    {
		    array_shift( $arguments );

			/*
		    if( is_array( current( $arguments ) ) )
		        $arguments = current( $arguments );
		    */

		    $query = self::quote( $query, $arguments );
	    }


	    #echo " --- QUERY: $query \r\n";
	    #echo '/* '.$query." */\n";
	    if( StickyDB::$echoQueries ){
	    	echo $query."\n";
	    }

	    if( StickyDB::$executeQueries ){

		    $connection = self::connect();

		    switch( self::ini('type') ){

		    	default:
		    	case'mysql':
				    $result = mysql_query( $query, $connection );
				    $error = mysql_error( $connection );
				    break;

				case'mysqli':
				    $result = mysqli_query( $connection, $query );
				    $error = mysqli_error( $connection );
					break;
			}

		    if( $error ){

		    	throw new Exception(

			    	'ERROR executing query: '."\n".
			    	$query."\n".
			    	$error."\n"

		    	);

		    }

		    return $result;
	    }
	    else{
	    	return null;
	    }
	}

	public static function insertId()
	{
		if( StickyDB::$executeQueries ){

		    switch( self::ini('type') ){
		    	default:
		    	case'mysql':
					return mysql_insert_id();

				case'mysqli':
					return mysqli_insert_id( self::connect() );
			}
		}
		else{
			return 1234;
		}
	}

	public static function fetch( $result )
	{
		if( is_resource( $result ) || $result instanceof mysqli_result ){

		    switch( self::ini('type') ){

		    	default:
		    	case'mysql':
					return mysql_fetch_assoc( $result );

				case'mysqli':
					return mysqli_fetch_assoc( $result );

			}
		}
		else if( is_array( $result ) ){

			$array = array();

			foreach( $result as $r ){
				$array[] = self::fetch( $r );
			}

			return $array;
		}
		else if( is_object( $result ) ){

			return $result->get();
		}
	}

	public static function fetchAll( $result )
	{
		$array = array();

		while( $data = self::fetch( $result ) ){
			$array[] = $data;
		}

		return $array;
	}

	public static function escape( $str )
	{
	    switch( self::ini('type') ){

	    	default:
	    	case'mysql':
				return mysql_escape_string( $str );

			case'mysqli':
				return mysqli_real_escape_string( self::connect(), $str );
		}
	}

	public static function quoteValue( $str )
	{
		if( is_null( $str ) ){
			return 'NULL';
		}
		else{
			$value = self::escape( $str );

			if( is_numeric( $value ) && $value === strval( floatval( $value ) ) ){
				;
			}
			else{
				$value = '"'.$value.'"';
			}

			return $value;
		}
	}

	public static function quote( $str, $values )
	{
		return self::quoteFrom( $str, $values );
	}

	public static function quoteFrom( $str, &$values )
	{
		if(!$values) {
			return $str;
		}

		$start = 0;

		while( ( $offset = strpos( $str, '?', $start ) ) !== false )
		{
			$value = array_shift( $values );

			if( is_array( $value ) ){

				$replacement = '( ';

				foreach( $value as $index => $list_value ){

					if( $index ){
						$replacement .= ', ';
					}

					$replacement .= self::quoteValue( $list_value );

				}

				$replacement .= ' )';
			}
			else{

				$replacement = self::quoteValue( $value );
			}

			$str = substr( $str, 0, $offset ).$replacement.substr( $str, $offset+1 );

			$start = $offset + strlen( $replacement );
		}

		return $str;
	}

	public static function split( $str )
	{
		$str = preg_replace('/(?!^)[[:upper:]][[:lower:]]/', '_$0', preg_replace('/(?!^)[[:upper:]]+/', '_$0', $str ));
		$str = strtolower( $str );

		return preg_split('/_+/',$str);
	}

	public static $iniData = false;

	public static function ini( $key, $default = null )
	{
		if( !stickydb::$iniData ){

			$inis = array(
				'ini.php',
				);

			$remoteAddress = $_SERVER['REMOTE_ADDR'];

			if( $remoteAddress == '127.0.0.1' or strrpos( $remoteAddress, '::1') == ( strlen( $remoteAddress ) - strlen( '::1' ) ) ){
				$inis[] = 'ini.local.php';
			}
			else{
				$inis[] = 'ini.remote.php';
			}

			$inis[] = 'ini.test.php';

			foreach( $inis as $ini ){

				$iniData = @parse_ini_file( dirname( __FILE__ ) . '/' . $ini, true );

				if( $iniData !== false ){

					stickydb::$iniData = $iniData;
					break;
				}
			}
		}

		$section = 'default';

		$sectionData = @stickydb::$iniData[ $section ];

		if( $sectionData )
		{
			$value = @$sectionData[ $key ];

			if( is_null( $value ) )
				return $default;
			else
				return $value;
		}
		else
			return $default;
	}

	public static function toTimestamp( $time=null ){
		if( $time === null ){
			$time = time();
		}
		return @date( 'Y-m-d H:i:s', $time );
	}

	public static function fromTimestamp( $str ){
		if( strpos( $str, '00' ) === 0 ){
			return null;
		}
		else{
			return @strtotime( $str );
		}
	}

	public function nullify( &$data = null, $fields = null ){

		if( isset( $this ) ){

			$fields = $data;

			if( is_null( $fields ) ){
				$fields = array_keys( $this->fields );
			}
			else if( is_string( $fields ) ){
				$fields = array( $fields );
			}

			$change = false;

			foreach( $fields as $field ){

				if( $this->get( $field ) == false ){
					if( $this->set( $field, null ) ){
						$change = true;
					}
				}
			}

			return $change;
		}
		else{

			if( is_array( $data ) ){

				if( is_null( $fields ) ){

					$fields = array_keys( $data );
				}
				else if( is_string( $fields ) ){
					$fields = array( $fields );
				}

				foreach( $fields as $field ){
					if( empty( $data[ $field ] ) ){
						$data[ $field ] = null;
					}
				}
			}

			return $data;
		}
	}

	public static function lock( $type = 'WRITE' ){

		$q = 'LOCK TABLES '.self::tick( self::makeTableName() ).' '.$type;

		self::query( $q );
	}

	public static function unlock(){

		$q = 'UNLOCK TABLES';

		self::query( $q );
	}

	public function __toString(){

		return self::makeClassName().' # '.$this->getPrimaryKey();
	}

	// INSERT GENERATED METHODS
}

// END OF CLASS DECLARATION




