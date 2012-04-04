<?php
 /**
  * Class for parsing and accessing NetCDF files and data.  This file provides
  * the user with the ability to load a NetCDF file into memory, parse
  * the header attributes, and return byte-addressable data without loading
  * the entire document into memory.
  *
  * @file NetCDF.php
  * @package Parsers
  * @date 2010-06-03 17:04 PDT
  * @author Paul Reuter
  * @version 1.2.2
  * @filesource
  *
  * @modifications <pre>
  *  1.0.0 - 2009-05-19 - Created
  *  1.0.1 - 2009-05-20 - First release
  *  1.0.2 - 2009-05-21 - phpDocumentor style comments
  *  1.0.3 - 2009-05-22 - Pretty formatting, very cosmetic stuff
  *  1.0.4 - 2009-06-11 - BugFix: getLength returned invalid dim_length
  *                       BugFix: FILL_DOUBLE was invalid hexidecimal
  *  1.0.5 - 2009-06-11 - BugFix: return numrecs for record dim, not dim_length
  *  1.1.0 - 2009-06-11 - API: open/close construct for multiple file access.
  *  1.1.1 - 2010-05-11 - BugFix: recsize improperly calculated.
  *  1.1.1 - 2010-05-11 - UNTIL FURTHER NOTICE, getValue* METHODS DO NOT WORK.
  *  1.2.0 - 2010-05-12 - BugFix, API change: getValues takes start count.
  *  1.2.1 - 2010-06-02 - Add: isTime($vname) check; bugfix, close bugfix
  *  1.2.2 - 2010-06-03 - Add: bytes = getHeaderLength(); aka data start offset
  *  </pre>
  *
  * @note <pre>
  *  Generate documentation with:
  *    phpdoc -f ..\NetCDF.php -t output\NetCDF -dn \
  *    NetCDF -o HTML:Smarty:HandS,PDF:default:default -pp off \
  *    -ti "NetCDF Reader for PHP" -ct date,file,modifications,note
  *  </pre>
  */
 
 
/**#@+
 * Enum data types.
 */
define("NC_BYTE",   1, true); //!< data is array of 8 bit signed integer
define("NC_CHAR",   2, true); //!< data is array of characters, i.e., text
define("NC_SHORT",  3, true); //!< data is array of 16 bit signed integer
define("NC_INT",    4, true); //!< data is array of 32 bit signed integer
define("NC_FLOAT",  5, true); //!< data is array of IEEE single precision float
define("NC_DOUBLE", 6, true); //!< data is array of IEEE double precision float
/**#@-*/
 
 
/**#@+
 * NetCDF flags.
 */
define("NC_DIMENSION", 10, true); //!< Flag indicating bytes are a dimension
define("NC_VARIABLE",  11, true); //!< Flag indicating bytes are a variable
define("NC_ATTRIBUTE", 12, true); //!< Flag indicating bytes are an attribute
/**#@-*/
 
 
/**#@+
 * Default fill values.
 */
define("FILL_CHAR",   0x00, true);
define("FILL_BYTE",   0x81, true);
define("FILL_SHORT",  0x8001, true);
define("FILL_INT",    0x80000001, true);
define("FILL_FLOAT",  0x7cf00000, true);
define("FILL_DOUBLE", -9999.99, true);
// define("FILL_DOUBLE", 0x479e00000000, true);
/**#@-*/
 
 
/**
 * A NetCDF class which allows direct access to NetCDF files without the
 * memory overhead.
 *
 * @package Parsers
 */
class NetCDF { 
 
  /**#@+
   * @access private
   */
  var $header;  //!< header[<vname>||__GLOBAL__]{type:<.>,length:<.>,attrs:{.}}
  var $byteos;  //!< Hash meta array containing named byte offsets and lengths
  var $fp;      //!< Open file pointer
  var $rfuncs;  //!< Lambda functions for altering data
  /**#@-*/
 
 
  /**
   * Constructor.  Load a NetCDF file's structure into memory.
   *
   * @access public
   * @param string $fpath A path to a NetCDF file existing on disk.
   * @return object 
   */
  function NetCDF($fpath) {
    if( !$this->open($fpath) ) { 
      return false;
    }
    register_shutdown_function(array($this,'__shutdown__'));
    return $this;
  } // END: function NetCDF($fpath)
 
 
 
  /**
   * Open a netcdf file and parse its header.
   *
   * @access public
   * @param string $fpath A path to a NetCDF file exsting on disk.
   * @return bool success or failure.
   */
  function open($fpath) { 
    if( !file_exists($fpath) ) { 
      trigger_error("File not found: $fpath",E_USER_ERROR);
      return false;
    }
    $this->byteos = array();
    $this->fp = fopen($fpath,'rb');
    if( !$this->fp ) { 
      trigger_error("Couldn't open file for reading: $fpath",E_USER_ERROR);
      return false;
    }
    if( !$this->m_init_header() ) { 
      trigger_error("Couldn't read header: $fpath",E_USER_ERROR);
      return false;
    }
    return true;
  } // END: function open($fpath)
 
 
 
  /**
   * Test a variable to determine if it has time units.
   *
   * @see http://www.unidata.ucar.edu/software/udunits/udunits-2/udunits2lib.html#Grammar
   * @see TimeUtils::unitref
   *
   * @access public
   * @param string $vname A variable name.
   * @return bool true if vname has attr units matching time, false otherwise
   */
  function isTime($vname) {
    $units = $this->getAttribute($vname,"units");
    if( $units == null ) { 
      return false;
    }
 
    $step  = '((?:s(?:ec(?:ond)?)?|'.
             'min(?:ute)?|'.
             'h(?:ou)?r|'.
             'd(?:ay)?|'.
             'mo(?:n(?:th)?)?|'.
             'y(?:ea)?r)s?)';
 
    $year   = '([+-]?[0-9]{1,4})';
    $month  = '(0?[1-9]|1[0-2])';
    $day    = '(0?[1-9]|[1-2][0-9]|30|31)';
    $hour   = '([+-]?[0-1]?[0-9]|2[0-3])';
    $minute = '([0-5]?[0-9])';
    $second = '((?:[0-5]?[0-9]|60)(?:\.[0-9]*)?)';
 
    // $date = '([+-]?)(\d{1,4})\-(\d{1,2})(?:\-(\d{1,2}))?';
    $date  = $year.'\-'.$month.'(?:\-'.$day.')?';
 
    // $clock = '([+-]?)(\d{1,2})\:(\d{1,2})(?:\:(\d{1,2}))?';
    $clock = $hour.'\:'.$minute.'(?:\:'.$second.')?';
 
    // $stamp = '([+-]?)(\d{1,4})(?:(\d{1,2})(\d{1,2})?)?T'.
    //          '([+-]?)(\d{1,2})(?:(\d{1,2})(\d{1,2})?)?';
    $stamp = $year.'(?:'.$month.'(?:'.$day.')?)?T'.
             $hour.'(?:'.$minute.'(?:'.$second.')?)?';
 
    $int   = '([+-]?\d+)';
    $id    = '([a-zA-Z_][a-zA-Z0-9_]*)';
    $shift = '(since|from|after|ref|\@)';
 
    $timestamp = '((?:'.$date.')|'.                                // 6, 7
                 '(?:'.$date.'(?:\s+|T)'.$clock.')|'.              // 12, 13
                 '(?:'.$date.'(?:\s+|T)'.$clock.'\s*'.$clock.')|'. // 21, 22
                 '(?:'.$date.'(?:\s+|T)'.$clock.'\s*'.$int.')|'.   // 29
                 '(?:'.$date.'(?:\s+|T)'.$clock.'\s*'.$id.')|'.    // 36
                 '(?:'.$date.'\s*'.$id.')|'.                       // 40
                 '(?:'.$stamp.')|'.                                // 44,45,46
                 '(?:'.$stamp.'\s*'.$int.')|'.                     // 53
                 '(?:'.$stamp.'\s*'.$id.'))';                      // 60
 
    $pat0 = '/^\s*(\d*)\s*'.$step.'\s+'.$shift.'\s+'.$timestamp.'\s*$/i';
    return (preg_match($pat0,$units));
  } // END: function isTime($vname)
 
 
 
  /**
   * Get an attribute for a variable by name.
   *
   * @access public
   * @param string $vname A variable name.
   * @param string $key An attribute name for that variable.
   * @return mixed The value for the attribute.
   */
  function getAttribute($vname,$key) {
    if( isset($this->header)
    && isset($this->header[$vname])
    && is_array($this->header[$vname])
    && isset($this->header[$vname]['attrs'])
    && is_array($this->header[$vname]['attrs'])
    && isset($this->header[$vname]['attrs'][$key])
    && is_array($this->header[$vname]['attrs'][$key])
    && isset($this->header[$vname]['attrs'][$key]['values']) ){
      return $this->header[$vname]['attrs'][$key]['values'];
    }
    return null;
  } // END: function getAttribute($vname,$key)
 
 
 
  /**
   * Get all attributes for a variable by name.
   *
   * @access public
   * @param string $vname A variable name.
   * @return array A hash of key:value attribute pairs.
   */
  function getAttributes($vname) {
    if( isset($this->header[$vname]) 
    && isset($this->header[$vname]['attrs'])) {
      $result = array();
      foreach( array_keys($this->header[$vname]['attrs']) as $k ) {
        if( is_array($this->header[$vname]['attrs'][$k])
        && isset($this->header[$vname]['attrs'][$k]['values']) ) { 
          $result[$k] = $this->header[$vname]['attrs'][$k]['values'];
        }
      }
      return $result;
    }
    return null;
  } // END: function getAttributes($vname)
 
 
 
  /**
   * Get a global attribute by key name.
   *
   * @access public
   * @param string $key A global attribute's name (eg: title, source, ...)
   * @return mixed The value of the global attribute.
   */
  function getGlobalAttribute($key) {
    return $this->getAttribute('__GLOBAL__',$key);
  } // END: function getGlobalAttribute($key)
 
 
  /**
   * Get all global attributes.
   *
   * @access public
   * @return array A hash array of key:value global attribute pairs.
   */
  function getGlobalAttributes() {
    return $this->getAttributes('__GLOBAL__');
  } // END: function getGlobalAttributes()
 
 
 
  /**
   * Get a list of variable names contained in the NetCDF file.
   *
   * @access public
   * @return array An array of variable names.
   */
  function getVariables() {
    if( isset($this->header) && is_array($this->header) ) {
      $vars = array_keys($this->header);
      $dims = $this->getDimensions();
      $ret = array();
      foreach($vars as $vname) { 
        if( !in_array($vname,$dims) && $vname != '__GLOBAL__') { 
          $ret[] = $vname;
        }
      }
      return $ret;
    }
    return null;
  } // END: function getVariables()
 
 
 
  /**
   * Get a list of dimensions.  If $vname is specified, will only return
   * the dimensions for the variable.
   *
   * @access public
   * @param string $vname (optional) A variable name.
   * @return array An array of dimensions contained by the NetCDF file.  If
   *    $vname was specified, returns an array of dimensions used by
   *    the variable.
   */
  function getDimensions($vname=null) {
    $dims = array();
    foreach(array_keys($this->header) as $name) {
      if( $this->m_getHeaderAttribute($name,'dim_length') !== null ) { 
        $dims[] = $name;
      }
    }
    if( is_null($vname) ) {
      return $dims;
    }
 
    $dimids = $this->m_getHeaderAttribute($vname,'dimids');
    if( is_array($dimids) ) { 
      $ret = array();
      foreach($dimids as $i) { 
        $ret[] = $dims[$i];
      }
      return $ret;
    }
 
    return array();
  } // END: function getDimensions($vname=null)
 
 
 
  /**
   * Get the $ix'th value for the variable $vname.  $ix starts at zero.
   *
   * @access public
   * @param string $vname A variable name.
   * @param uint $ix The ix'th value to return, starting from zero.
   * @return mixed The {nc_type} value at $ix'th position of $vname.
   */
  function getValue($vname,$ix) {
    $result = $this->getValues($vname,array($ix));
    return (is_array($result) && count($result)==1) ? $result[0] : false;
  } // END: function getValue($vname,$ix)
 
 
 
  /**
   * Return an array of values specified by start and count.  If $count
   * not specified, returns all values of $vname starting from $start.
   * If a dimension of $count is not specified, and that same dimension of
   * $start is not specified, returns a value for each dimension-coordinate,
   * otherwise if $start[i] was specified, returns 1 value for that dim-coord.
   * If $start is not specified, assumes start is zero.
   *
   * @access public
   * @param string $vname A variable name.
   * @param array $start A list of start indexes, one per dimension.
   * @param array $count A list of counts (number of of values in this dim.
   * @return array An array of extracted values.
   */
  function getValues($vname,$start=null,$count=null) {
    $dims = $this->getDimensions($vname);
    $ndim = count($dims);
    if( $count===null ) {
      // Return all if start not specified, 1 if start specified.
      $fill = ($start===null) ? 0 : 1;
      $count = $ndim > 0? array_fill(0,$ndim,$fill): array();
    }
    if( $start===null ) {
      // start at zero
      $start = $ndim > 0? array_fill(0,$ndim,0): array();
    }
 
    if( !is_array($start) || count($start) !== $ndim ) {
      trigger_error(
        "getValues: Expecting start to be an array.", E_USER_ERROR
      );
      return false;
    }
    if( !is_array($count) || count($count) !== $ndim ) {
      trigger_error(
        "getValues: Expecting count to be an array.", E_USER_ERROR
      );
      return false;
    }
    for($i=0; $i<$ndim; $i++) {
      $len = $this->getLength($dims[$i]);
      if( $start[$i] >= $len ) {
        trigger_error(
          "getValues: (".$dims[$i].") start index ".$start[$i].
          " out of bounds [0,$len).",
          E_USER_ERROR
        );
        return false;
      }
      if( $start[$i] + $count[$i] > $len ) {
        trigger_error(
          "getValues: (".$dims[$i].")".$start[$i].' + '.$count[$i].
          " out of bounds [0,$len).",
          E_USER_ERROR
        );
        return false;
      }
      if( $count[$i] <= 0 ) {
        // automatically go to end of record.
        $count[$i] = $len - $start[$i];
      }
    }
 
    $nc_type = $this->m_getHeaderAttribute($vname,'nc_type');
    $dsize = $this->m_sizeof($nc_type);
    $result = array();
    $reslen = 0;
    $start_os = $this->m_getByteOffset($vname);
    $ixs = $this->m_getIndexsFromVarStartStartCount($vname,$start,$count);
 
    if( $this->isRecordVariable($vname) ) { 
      $pvect = $this->m_getHeaderAttribute($vname,'product_vector');
      $v0 = $pvect[0];
      $v1 = (count($pvect)>1) ? $pvect[1] : 1;
      foreach( $ixs as $ix_len ) { 
        list($ix,$len) = $ix_len;
        $c0 = (int)($ix/$v1);
        $ix = $ix % $v1;
        $os = $start_os + ($dsize * $ix) + ($c0 * $this->recsize);
        // read multiple at once.
        array_splice($result,$reslen,0,$this->m_read_ncdata($nc_type,$os,$len));
        $reslen += $len;
      }
    } else { 
      foreach($ixs as $ix_len) {
        list($ix,$len) = $ix_len;
        $os = $start_os + $dsize*$ix;
        array_splice($result,$reslen,0,$this->m_read_ncdata($nc_type,$os,$len));
        $reslen += $len;
      }
    }
    return $result;
  } // END: function getValues(...)
 
 
  function m_getIndexsFromVarStartStartCount($vname,$start,$count) { 
    $ndim = count($start);
    if( $ndim < 1 ) { 
      return array(0);
    }
 
    // The number of elements to extract
    // Number of sequential blobs to extract
    $nc = 1;
    for($i=$ndim-2; $i>=0; $i--) { 
      $nc *= $count[$i];
    }
 
    $coords = array();
    for($i=0; $i<$nc; $i+=1) { 
      $coord = $start;
      $index = $i * $count[$ndim-1];
      for($j=$ndim-1; $j>=0; $j--) { 
        $coord[$j] = $start[$j] + ($index % $count[$j]);
        $index = (int)($index/$count[$j]);
      }
      $coords[] = $coord;
    }
 
    $ixs = array();
    $i=0;
    $pvect = $this->m_getHeaderAttribute($vname,'product_vector');
    foreach($coords as $coord) { 
      // Begin: coordinate locate
      $innerProduct = 0;
      $ix = $coord[$ndim-1];
      for($p=$ndim-1; $p>0; $p--) { 
        $ix += $pvect[$p] * $coord[$p-1];
      }
      if( $i>0 && $ixs[$i-1][0] + $ixs[$i-1][1] == $ix ) { 
        $ixs[$i-1][1] += $count[$ndim-1];
      } else { 
        $ixs[$i++] = array($ix,$count[$ndim-1]);
      }
    }
 
    return $ixs;
  } // END: function m_getIndexsFromVarStartStartCount(...)
 
 
  /**
   * Determine if $vname is the record (unlimited) dimension.
   *
   * @param string $vname A dimension name.
   * @return bool true if $vname is the unlimited dimension, false otherwise.
   */
  function isRecordDimension($vname) { 
    return( $this->m_getHeaderAttribute($vname,"dimid")===$this->recdimid );
  } // END: function isRecordDimension($vname)
 
 
  /**
   * Determine if $vname is the record (unlimited) dimension.
   *
   * @param string $vname A dimension name.
   * @return bool true if $vname is the unlimited dimension, false otherwise.
   */
  function isRecordVariable($vname) {
    $dimids = $this->m_getHeaderAttribute($vname,"dimids");
    if( !empty($dimids) && $dimids[0]===$this->recdimid ) {
      return true;
    }
    return false;
  } // END: function isRecordVariable($vname)
 
 
  /**
   * Get the index into the dimension for the specified $value.  Performs
   * a b-tree search on sorted dimension values.  If $value is not found,
   * the value of $resolve will determine the result.  When $resolve is true,
   * an index will be returned as close to $value as possible.  When false,
   * will return false.
   *
   * @access public
   * @param string $dname A dimension name.
   * @param {nc_type} $value A value to search for.  Floats are compared to
   *   6 decimal places of precision.
   * @param bool $resolve Whether the closest value should be returned if
   *    $value wasn't found. (default=true)
   * @return mixed Returns the index in dimension's values where value was
   *    found or computed, false if not found.
   */
  function getIndexFromValue($dname,$value,$resolve=true) { 
    $dims = $this->getDimensions();
    if( !in_array($dname,$dims) ) { 
      trigger_error("Variable must be a dimension.",E_USER_ERROR );
      return false;
    }
    $values = $this->getValues($dname);
    return $this->m_indexOf($values,$value,$resolve);
  } // END: function getIndexFromValue($dname,$value,$resolve=true)
 
 
 
  /**
   * Get the number of values stored for all dimensions of $vname.
   *
   * @access public
   * @param string $vname A variable name.
   * @return uint Number of values stored by $vname.  This is the same
   *    as computing length(dim[0]) * length(dim[1]) ...
   */
  function getLength($vname) {
    $dim_length = $this->m_getHeaderAttribute($vname,'dim_length');
    if( $dim_length !== null ) {
      return $dim_length;
    }
    $nc_type = $this->m_getHeaderAttribute($vname,'nc_type');
    $bytes = $this->m_sizeof($nc_type);
    return ceil($this->m_getHeaderAttribute($vname,'vsize')/$bytes);
  } // END: function getLength($vname)
 
 
 
  /**
   * Get the data type of the variable.  The data type is an NC_* enum type.
   *
   * @access public
   * @param string $vname A variable name.
   * @return enum (NC_BYTE|NC_CHAR|NC_SHORT|NC_INT|NC_FLOAT|NC_DOUBLE)
   */
  function getType($vname) {
    $nc_type = $this->m_getHeaderAttribute($vname,'nc_type');
    return (is_Null($nc_type)) ? false : $nc_type;
  } // END: function getType($vname)
 
 
 
  /**
   * Get a string representation of the type of the variable.
   *
   * @access public
   * @param string $vname A variable name.
   * @return string A text description of the data type.
   */
  function getTypeName($vname) { 
    return $this->m_typeToString($this->getType($vname));
  } // END: function getTypeName($vname)
 
 
 
  /**
   * Programmatic access to NetCDF file pointer.  Allow the file to be
   * closed.  Useful when scripting file access to several .nc files
   * in sequence.
   *
   * @access public
   * @return bool always true.
   */
  function close() { 
    if( $this->fp ) { 
      if( is_resource($this->fp) ) { 
        fclose($this->fp);
      }
      $this->fp = null;
    }
    return true;
  } // END: function close()
 
 
 
  /**
   * Return the number of bytes consumed by the header.
   *
   * @access public
   * @return uint Number of bytes used for the header.
   */
  function getHeaderLength() { 
    // Find where this element starts in the file, and how big it is.
    list($var_start,$var_length) = $this->byteos['var_list'];
    return $var_start + $var_length;
  } // END: function getHeaderLength()
 
 
 
  // ------------------------------------------------------------------------
  //   Private parsing routines
  // ------------------------------------------------------------------------
 
 
 
  /**
   * Initialize the header data.  This is called on-load, and is required
   * in order to compute byte offsets for values.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_header() {
    $this->header = array();
    $this->recsize = 0;
    $this->recdimid = 0;
    if( !$this->m_init_magic() ) { 
      return false;
    }
    if( !$this->m_init_numrecs() ) { 
      return false;
    }
    if( !$this->m_init_dim_list() ) { 
      return false;
    }
    if( !$this->m_init_gatt_list() ) { 
      return false;
    }
    if( !$this->m_init_var_list() ) { 
      return false;
    }
    return true;
  }  // END: function m_init_header()
 
 
 
  /**
   * Test and initialize the magic portion of the header.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_magic() {
    // define where this object starts in the file, and how many bytes it is.
    $this->byteos['magic'] = array(0,4);
 
    fseek($this->fp,0);
    if( 'CDF' !== fread($this->fp,3) ) { 
      trigger_error("Magic CDF not found.",E_USER_ERROR);
      return false;
    }
    $this->version = ord(fread($this->fp,1));
    if( !($this->version==1 || $this->version==2) ) { 
      trigger_error(
        "magic: Version not supported (".$this->version.")",
        E_USER_ERROR
      );
    }
    return true;
  }  // END: function m_init_magic()
 
 
 
  /**
   * Get the number of variables stored by the file.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_numrecs() {
    // Find where this element starts in the file, and how big it is.
    list($magic_start,$magic_length) = $this->byteos['magic'];
    $start = $magic_start + $magic_length;
 
    fseek($this->fp,$start);
    $this->numrecs = $this->m_read_uint32();
 
    $this->byteos['numrecs'] = array($start, 4);
    return true;
  } // END: function m_init_numrecs()
 
 
 
  /**
   * Parse the list of dimension names and attributes.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_dim_list() { 
    // Find where this element starts in the file, and how big it is.
    list($numrecs_start,$numrecs_length) = $this->byteos['numrecs'];
    $start = $numrecs_start + $numrecs_length;
 
    fseek($this->fp,$start);
    $flag   = $this->m_read_uint32();
    $nelems = $this->m_read_uint32();
    $length = 8;
 
    if( !($flag == 0 || $flag == NC_DIMENSION) ) { 
      trigger_error(
        "dim_list: Expecting ABSENT or NC_DIMENSION.", E_USER_ERROR
      );
      return false;
    }
 
    if( $flag == 0 && $nelems != 0 ) {
      trigger_error(
        "dim_list: Expecting ABSENT, found non-zero nelems.", E_USER_ERROR
      );
      return false;
    }
 
    $dimid = 0;
    while( $nelems > 0 ) {
      $dim_bytes = $this->m_init_dim($dimid);
      $length += $dim_bytes;
      $nelems -= 1;
      $dimid += 1;
    }
 
    $this->byteos['dim_list'] = array($start, $length);
    return true;
  } // END: function m_init_dim_list()
 
 
 
  /**
   * Parse the list of global attributes.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_gatt_list() { 
    // Find where this element starts in the file, and how big it is.
    list($dlist_start,$dlist_length) = $this->byteos['dim_list'];
    $start = $dlist_start + $dlist_length;
    
    fseek($this->fp,$start);
    $flag   = $this->m_read_uint32();
    $nelems = $this->m_read_uint32();
    $length = 8;
 
    if( !($flag == 0 || $flag == NC_ATTRIBUTE) ) { 
      trigger_error(
        "gatt_list: Expecting ABSENT or NC_ATTRIBUTE.", E_USER_ERROR
      );
      return false;
    }
 
    if( $flag == 0 && $nelems != 0 ) {
      trigger_error(
        "gatt_list: Expecting ABSENT, found non-zero nelems.", E_USER_ERROR
      );
      return false;
    }
 
    while( $nelems > 0 ) {
      $att_bytes = $this->m_init_att('__GLOBAL__');
      $length += $att_bytes;
      $nelems -= 1;
    }
 
    $this->byteos['gatt_list'] = array($start, $length);
    return true;
  } // END: function m_init_gatt_list()
 
 
 
  /**
   * Parse the list of variables and their attributes.
   *
   * @access private
   * @return bool success or failure.
   */
  function m_init_var_list() { 
    // Find where this element starts in the file, and how big it is.
    list($gatt_start,$gatt_length) = $this->byteos['gatt_list'];
    $start = $gatt_start + $gatt_length;
    
    fseek($this->fp,$start);
    $flag   = $this->m_read_uint32();
    $nelems = $this->m_read_uint32();
    $length = 8;
 
    if( !($flag == 0 || $flag == NC_VARIABLE) ) { 
      trigger_error(
        "var_list: Expecting ABSENT or NC_VARIABLE.", E_USER_ERROR
      );
      return false;
    }
 
    if( $flag == 0 && $nelems != 0 ) {
      trigger_error(
        "var_list: Expecting ABSENT, found non-zero nelems.", E_USER_ERROR
      );
      return false;
    }
 
    while( $nelems > 0 ) {
      $var_bytes = $this->m_init_var();
      $length += $var_bytes;
      $nelems -= 1;
    }
 
    $this->byteos['var_list'] = array($start, $length);
    return true;
  } // END: function m_init_var_list()
 
 
 
  /**
   * Parse a single dimension "object".  Get its name and length.
   *
   * @access private
   * @return uint Number of bytes occupied by this dim definition.
   */
  function m_init_dim($dimid) {
    $start = ftell($this->fp);
    $nelems = $this->m_read_uint32();
    $name = fread($this->fp,$nelems);
    if( $nelems%4 != 0 ) { 
      fseek($this->fp,4-($nelems%4),SEEK_CUR);
    }
    $dim_length = $this->m_read_uint32();
    if( $dim_length===0 ) { 
      $dim_length = $this->numrecs;
      $this->recdimid = $dimid;
    }
    $this->m_setHeaderAttribute($name,'dim_length',$dim_length);
    $this->m_setHeaderAttribute($name,'dimid',$dimid);
    return (ftell($this->fp) - $start);
  } // END: function m_init_dim()
 
 
 
  /**
   * Parse a single attribute "object".  Get its name, nc_type, and contents.
   *
   * @access private
   * @return uint Number of bytes occupied by this att definition.
   */
  function m_init_att($vname) {
    $start = ftell($this->fp);
    $nelems = $this->m_read_uint32();
    $name = fread($this->fp,$nelems);
    if( $nelems%4 != 0 ) { 
      fseek($this->fp,4-($nelems%4),SEEK_CUR);
    }
 
    $nc_type = $this->m_read_uint32();
    $nelems = $this->m_read_uint32();
 
    if( $nc_type == NC_CHAR ) { 
      $values = fread($this->fp,$nelems);
    } else {
      if( $nelems == 1 ) { 
        list($values) = $this->m_read_ncdata($nc_type,null,1);
      } else { 
        $values = $this->m_read_ncdata($nc_type,null,$nelems);
      }
    }
 
    $asize = $nelems * $this->m_sizeof($nc_type);
    if( $asize%4 != 0 ) { 
      fseek($this->fp,4-($asize%4),SEEK_CUR);
    }
 
    $this->m_setVariableAttribute(
      $vname, $name, array('nc_type'=>$nc_type,'values'=>$values)
    );
    return (ftell($this->fp) - $start);
  } // END: function m_init_att($vname)
 
 
  /**
   * Parse a single variable "object".  Get its name, dimids, attributes,
   *  type, size and byte offset.
   *
   * @access private
   * @return uint Number of bytes occupied by this var definition.
   */
  function m_init_var() {
    $start = ftell($this->fp);
    $nelems = $this->m_read_uint32();
    $name = fread($this->fp,$nelems);
    if( $nelems%4 != 0 ) { 
      fseek($this->fp,4-($nelems%4),SEEK_CUR);
    }
 
    $nelems = $this->m_read_uint32();
    $dimids = array();
    while( $nelems > 0 ) { 
      $dimids[] = $this->m_read_uint32();
      $nelems -= 1;
    }
    $this->m_setHeaderAttribute($name,'dimids',$dimids);
 
 
    $flag  = $this->m_read_uint32();
    $nelems = $this->m_read_uint32();
 
    if( !($flag == 0 || $flag == NC_ATTRIBUTE) ) { 
      trigger_error(
        "att_list: Expecting ABSENT or NC_ATTRIBUTE.", E_USER_ERROR
      );
      return false;
    }
 
    if( $flag == 0 && $nelems != 0 ) {
      trigger_error(
        "att_list: Expecting ABSENT, found non-zero nelems.", E_USER_ERROR
      );
      return false;
    }
 
    while( $nelems > 0 ) {
      $this->m_init_att($name);
      $nelems -= 1;
    }
 
    $nc_type = $this->m_read_uint32();
    $this->m_setHeaderAttribute($name,'nc_type',$nc_type);
 
    $vsize = $this->m_read_uint32();
    $this->m_setHeaderAttribute($name,'vsize',$vsize);
 
    $begin = $this->m_read_uint32();
    $this->m_setHeaderAttribute($name,'begin',$begin);
 
    // Compute record size
    if( !empty($dimids) && $dimids[0]==$this->recdimid ) { 
      $this->recsize += $vsize;
    }
 
    // Pre-compute product vector
    $product = 1;
    $dimnames = $this->getDimensions($name);
    $n = count($dimnames);
    $pvect = $n > 0? array_fill(0,$n,1): array();
    for($i=$n; $i>0; $i--) {
      $product *= $this->m_getHeaderAttribute($dimnames[$i-1],'dim_length');
      $pvect[$i-1] = $product;
    }
    /*
    if( $this->isRecordDimension($dimnames[0]) ) { 
      $pvect[0] = 0;
    }
    */
    $this->m_setHeaderAttribute($name,'product_vector',$pvect);
 
    return (ftell($this->fp) - $start);
  } // END: function m_init_var()
 
 
 
  // ------------------------------------------------------------------------
  //   More private methods
  // ------------------------------------------------------------------------
 
 
 
  /**
   * Read an unsigned int from the file pointer at its current location.
   *
   * @access private
   * @return uint32 An unsigned 32-bit integer.
   */
  function m_read_uint32() { 
    $up = unpack('N',fread($this->fp,4));
    return $up[1];
  } // END: function m_read_uint32()
 
 
  /**
   * Assign a meta-attribute used for internal access.  These attributes
   * are assigned on a per-variable basis.
   *
   * @access private
   * @param string $vname A variable name.
   * @param string $attr An attribute name.
   * @param mixed $value Any value to assign.
   * @return bool Always true.
   */
  function m_setHeaderAttribute($vname,$attr,$value) { 
    if( !is_array($this->header) ) { 
      $this->header = array();
    }
    if( !isset($this->header[$vname]) || !is_array($this->header[$vname]) ) { 
      $this->header[$vname] = array();
    }
    if( !isset($this->header[$vname]['header'])
    || !is_array($this->header[$vname]['header']) ) { 
      $this->header[$vname]['header'] = array();
    }
    $this->header[$vname]['header'][$attr] = $value;
    return true;
  } // END: function m_setHeaderAttribute($vname,$attr,$value)
 
 
 
  /**
   * Retrieve a meta-attribute.
   *
   * @access private
   * @param string $vname A variable name.
   * @param string $attr An attribute name.
   * @return mixed $value Any value that was assign.
   *   If $vname or $attr wasn't found, returns null.
   */
  function m_getHeaderAttribute($vname,$attr) { 
    if( is_array($this->header)
    && isset($this->header[$vname])
    && is_array($this->header[$vname])
    && isset($this->header[$vname]['header'])
    && is_array($this->header[$vname]['header'])
    && isset($this->header[$vname]['header'][$attr]) ) { 
      return $this->header[$vname]['header'][$attr];
    }
    return null;
  } // END: function m_getHeaderAttribute($vname,$attr)
 
 
  /**
   * Retrieve all the meta-attributes for a given variable.
   *
   * @access private
   * @param string $vname A variable name.
   * @return mixed{} A hash of key:value pairs for the $vname variable.
   *   If $vname wasn't found, returns null.
   */
  function m_getHeaderAttributes($vname) { 
    if( is_array($this->header)
    && isset($this->header[$vname])
    && is_array($this->header[$vname])
    && isset($this->header[$vname]['header'])
    && is_array($this->header[$vname]['header']) ) {
      return $this->Header[$vname]['header'];
    }
    return null;
  } // END: function m_getHeaderAttributes($vname)
 
 
 
  /**
   * Assign a NetCDF-attribute.
   *
   * @access private
   * @param string $vname A variable name.
   * @param string $attr An attribute name.
   * @param <nc_tyep> $value A value to assign.
   * @return bool Always true.
   */
  function m_setVariableAttribute($vname,$attr,$value) { 
    if( !is_array($this->header) ) { 
      $this->header = array();
    }
    if( !isset($this->header[$vname]) || !is_array($this->header[$vname]) ) { 
      $this->header[$vname] = array();
    }
    if( !isset($this->header[$vname]['attrs'])
    || !is_array($this->header[$vname]['attrs']) ) { 
      $this->header[$vname]['attrs'] = array();
    }
    $this->header[$vname]['attrs'][$attr] = $value;
    return true;
  } // END: function m_setVariableAttribute($vname,$attr,$value)
 
 
 
  /**
   * Get the location in the NetCDF file where data for $vname begins.
   *
   * @access private
   * @param string $vname A variable name.
   * @return uint The byte offset usable in fseek($fp,$bye_offset,SEEK_SET)
   */
  function m_getByteOffset($vname) {
    $begin = $this->m_getHeaderAttribute($vname,'begin');
    return $this->m_getHeaderAttribute($vname,'begin');
  } // END: function m_getByteOffset($vname)
 
 
 
  /**
   * swithes the read mechanism based on nc_type, a data type.
   * NetCDF data is stored in Big Endian notation, special care is needed.
   *
   * @access private
   * @param enum $nc_type A valid NetCDF NC_* data type.
   * @param uint $seek Location in file to seek to (default=current).
   * @param uint $count A number > 0 indicating number of data to read.
   * @return {nc_type} A value pulled from the file.
   */
  function m_read_ncdata($nc_type,$seek=null,$count=1) {
    if( is_numeric($seek) ) { 
      fseek($this->fp,$seek);
    }
    $so = $this->m_sizeof($nc_type);
    switch($nc_type) {
      case NC_BYTE:
        $up = unpack("C*",fread($this->fp,$so*$count));
        return array_values($up);
      case NC_CHAR:
        // NetCDF does not store strings as null-terminated.
        if( method_exists('str_split') ) { 
          return str_split('',fread($this->fp,$so*$count));
        } else { 
          return preg_split(
            '//', fread($this->fp,$so*$count), -1, PREG_SPLIT_NO_EMPTY
          );
        }
      case NC_SHORT:
        if( !isset($this->rfuncs[$nc_type]) ) { 
          $this->rfuncs[$nc_type] = create_function(
            '$val',
            'return ($val & 0x8000) ? $val | 0xffff0000 : $val;'
          );
        }
        $up = unpack("n*",fread($this->fp,2*$count));
        return array_values( array_map($this->rfuncs[$nc_type],$up) );
      case NC_INT:
        if( !isset($this->rfuncs[$nc_type]) ) { 
        // Supposedly, this hack will overcome PHP's signed int limitation.
          $this->rfuncs[$nc_type] = create_function(
            '&$val',
            'return ($val & 0x80000000) ? $val + 0xffffffff : $val;'
          );
        }
        $up = unpack("N*",fread($this->fp,4*$count));
        return array_values( array_map($this->rfuncs[$nc_type],$up) );
      case NC_FLOAT:
        $b = pack("I",0x01);
        if( !$b{0} ) { 
          // On big endian machine.  Data already big endian!
          return array_values(unpack("L*",fread($this->fp,4*$count)));
        }
        // Else on little endian machine.
 
        // Read as written (big endian)
        // Read unsigned 4-bytes big-ending
        $up = unpack("N*",fread($this->fp,4*$count));
        // Write unsigned machine-dependent (little-endian)
        array_splice($up,0,0,"L*");
        $bytes = call_user_func_array('pack',$up); // ("L*",$up);
        // Read it back: machine-dependent (little-endian) bytes as float
        return array_values(unpack("f*",$bytes));
      case NC_DOUBLE:
        $b = pack("I",0x01);
        if( !$b{0} ) { 
          // On big endian machine.  Data already big endian!
          return array_values(unpack("d*",fread($this->fp,8*$count)));
        }
        // Read as written (big endian), unsigned 4-byte words * 2
        // 8765 4321 => 2 numbers in memory.
        $up = unpack("N*",fread($this->fp,8*$count));
        // swap word order: 4321 8765
        for($i=1,$n=count($up); $i<=$n; $i+=2) { 
          list($up[$i],$up[$i+1]) = array($up[$i+1],$up[$i]);
        }
        // Write unsigned machine-dependent (little-endian)
        // 1234 5678 => 2 numbers in little-endian
        array_splice($up,0,0,"L*");
        $bytes = call_user_func_array('pack',$up); // ("L*",$up);
        // Read it back: machine-dependent (little-endian) bytes as float
        return array_values(unpack("d*",$bytes));
    }
    return false;
  } // END: function m_read_ncdata($nc_type,$seek=null,$count=1)
 
 
 
  /**
   * Get the number of bytes used by a NetCDF NC_* data type.
   *
   * @access private
   * @param enum $nc_type An NC_* value.
   * @return uint The number of bytes used by a single value of type {nc_type}
   */
  function m_sizeof($nc_type) {
    switch($nc_type) { 
      case NC_BYTE:   return 1;
      case NC_CHAR:   return 1;
      case NC_SHORT:  return 2;
      case NC_INT:    return 4;
      case NC_FLOAT:  return 4;
      case NC_DOUBLE: return 8;
    }
    return 4;
  }  // END: function m_sizeof($nc_type)
 
 
 
  /**
   * Get a text description of the NetCDF NC_* data type.
   *
   * @access private
   * @param enum $nc_type An NC_* enum value.
   * @return string The name of the data type.
   */
  function m_typeToString($nc_type) { 
    switch($nc_type) { 
      case NC_BYTE:   return "byte";
      case NC_CHAR:   return "char";
      case NC_SHORT:  return "short";
      case NC_INT:    return "int";
      case NC_FLOAT:  return "float";
      case NC_DOUBLE: return "double";
    }
    return false;
  } // END: function m_typeToString($nc_type)
 
 
 
  /**
   * Search for the index of $value in array $values.  If $value was not found
   *  in $values and $resolve is true, the function will return an index
   *  closest to the target value.  Performs a b-tree style approach to
   *  searching a list of sorted values.
   *
   * @access private
   * @param array $values An array of values that can be compared to $value.
   * @param mixed $value A value to search for.
   * @param bool $resolve Indicates whether we return the nearest index.
   * @param uint $lo The lowest index to test against.
   * @param uint $hi The highest index to test against.
   * @return uint An index where $value is located.  False when $value not
   *   found and $resolve is false.
   */
  function m_indexOf(&$values,$value,$resolve=false,$lo=null,$hi=null) {
    $lo = (is_null($lo)) ? 0 : $lo;
    $hi = (is_null($hi)) ? count($values) : $hi;
    if( $lo >= $hi ) {
      $nk = count($values);
      if( $lo >= $nk ) {
        return ($resolve) ? $lo : false;
      }
      $cmp = ($value<$values[$lo]) ? -1 : (($value>$values[$lo]) ? +1 : 0);
      return ($cmp===0||$resolve) ? ( ($cmp>0) ? ($lo+1) : $lo ) : false;
    }
    $mid = ($hi+$lo)>>1;
    $cmp = ($value<$values[$mid]) ? -1 : (($value>$values[$mid]) ? +1 : 0);
    if( $cmp === 0 ) {
      return $mid;
    }
    if( $cmp < 0 ) {
      return $this->m_indexOf($values,$value,$resolve,$lo,$mid-1);
    }
    return $this->m_indexOf($values,$value,$resolve,$mid+1,$hi);
  } // END: function m_indexOf(&$values,$value,$resolve=false,$lo=0,$hi=null)
 
 
 
  /**
   * Closes file handle when script terminates.
   *
   * @access private
   * @return bool Always true.
   */
  function __shutdown__() {
    $this->close();
    return true;
  } // END: function __shutdown__()
 
 
} // END: class NetCDF($fpath)
 
 
 
// EOF -- NetCDF.php
?>