<?php

# XML sub-document processing hack - return a chunk of your xml data.
# Use multiple instances to return chunks of chunks & drill down into simple data;
# or, use a DOM parser on the returned chunk for the more complex stuff

# Designed to be simpler to use than SAX parsing (I hope) and you don't have to 
# load a huge document tree into memory if you're using DOM.  

# Accepts string or file xml data, and searches for the next instance of a requested
# element - including attributes, cdata, and any sub-elements.  Returns a string of XML data.

# usage: 
# 		$obj = XSP::createFileParser($filename);
# or	$obj = XSP::createMemoryParser($xmlstring);
# then	$string = $obj->getNextElement('ename'); //looks for <ename>...</ename>
# 		$obj->cleanup;  // close file, free the xml_parser etc.

# send comments, suggestions, "shame-on-you-for-the-messy-hack" lectures,
# donuts, etc. to ged at gednet.com

# this is version 0.3

class XSP {
	
	function XSP() {
		$this->debug = 0;

		// # of bytes to feed xml_parse function at a time.  Needs to be tuned, but should
		// be fairly small - large chunks of data may contain two or more of the elements we're
		// searching for at once, and we'd end up returning the *last* instead of the 'next'.
		// no point in having this be less than 4 since strlen('</x>') = 4
		$this->parseChunkSize = 10;
	}

	// static
	function createFileParser($file) {
		if (!$file) return 0;
		$xspObj = new XSP();

		$xspObj->filename = $file;
		
		// disable magic_quotes_runtime if it's turned on
		set_magic_quotes_runtime(0);
	
		return $xspObj;
	}
	
	// static
	function createMemoryParser($xmldata) {
		if (!$xmldata) return 0;

		$xspObj = new XSP();

		$xspObj->fp = tmpfile();
		$xspObj->filename = 'tmpfile'; 
		fwrite($xspObj->fp, $xmldata); 
		rewind($xspObj->fp);

		//$xspObj->xmldata = $xmldata;
		
		// disable magic_quotes_runtime if it's turned on
		set_magic_quotes_runtime(0);
	
		return $xspObj;
	}

	function cleanup() {
		if ($this->fp) fclose($this->fp);
		if ($this->parser) xml_parser_free($this->parser);
	}

	function reset() {
		if ($this->filename) {
			if ($this->fp) { 
				rewind($this->fp);
			} else {
				$this->fp = @fopen($this->filename, 'r');
				if (!$this->fp) return 'ERROR: Failed to open file.';
			}
		}
		
		// $this->record holds additional data after the full record
		// has been read in (may include the start of the next record)
		unset ($this->record);

		if ($this->parser) xml_parser_free($this->parser);
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, &$this); 
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->parser, 'opening_element', 
			'closing_element');
		xml_set_character_data_handler($this->parser, 'cdata_handler');
	}

	function getNextElement($elementname = '', &$attribs, &$cdata) {
		if (!$elementname) return "ERROR: No element name specified";
		
		unset ($this->fullrecord, $this->inelement);
		$this->ename = $elementname;
		 
		if ($this->fp) {
			while ((!$this->fullrecord) && (!feof($this->fp))) {
				$data = fread($this->fp, $this->parseChunkSize);
				xml_parse($this->parser, $data);
			}
		} else {
			$xmlarry = $this->str_split($this->xmldata, $this->parseChunkSize);
			foreach ($xmlarry as $chunk) {
				xml_parse($this->parser, $chunk);
				if ($this->fullrecord) break;
			}
		}
		if (!$this->fullrecord) {
			$attribs = '';
			return 'ERROR: End of data reached before full record found.';
		} else {
			$attribs = $this->attribs;
			$cdata = $this->lastcdata;
			return $this->fullrecord;
		}
	}


	function opening_element($parser, $element, $attribs) {
		$this->cdata = '';
		
		if ($element == $this->ename) {
			if ($this->debug) echo "found one<br />";
			$this->inelement = true;
			$this->record = '';
			$this->attribs = $attribs;
		}
		
		if ($this->inelement) {
			$this->record .= "<$element";
			foreach ($attribs as $attrib => $value) { 
				$this->record .= " $attrib=\"$value\""; 
			}
			$this->record .= '>';
		}
	}
	
	function closing_element($parser, $element) {
		if (($this->inelement) && ($element == $this->ename)) {
			$this->lastcdata = $this->cdata;
		}
		
		if ($this->cdata) {
			$this->record .= '<![CDATA['.$this->cdata.']]>';
			$this->cdata = "";
		}
		$this->record .= "</$element>";
		
		if (($this->inelement) && ($element == $this->ename)) {
			// end of requested record
			$this->fullrecord = $this->record;
			unset ($this->record, $this->inelement);
		} 
	}
	
	function cdata_handler($parser, $data) {
		$this->cdata .= $data;
	}
	
	function str_split($string, $length=1) {
		// fulfills the (otherwise PHP v5+) str_split function - see php docs
		// thanks to k at skreel dot org (10-Jul-2003 01:36) for the function
		$inc= (int)$length;
		if ($inc > 0) { 
			$rtn = array();
			$offset = 0;
			$limit = strlen($string);
			while ($offset < $limit) { 
				$rtn[]= substr($string, $offset, $inc);
				$offset += $inc;
			}
		} else {
			$rtn = '';
		}
		return ($rtn);
	}

	
}
 	
		
?>