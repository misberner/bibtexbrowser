<?php

@require_once('local-pdfs.config.php');

function getLocalPdfDirMtime() {
	static $mtime = -1;
	if ($mtime == -1) {
		$mtime = filemtime(LOCAL_PDF_DIR);
	}
	return $mtime;
}

function extractHost($url) {
	$matches = array();
	if (!preg_match('@^http\://(?:[^/]*\.)?([^/\.]+\.[^/\.]+)/@', $url, $matches)) {
		return null;
	}
	if (!isset($matches[1])) {
		return null;
	}
	return $matches[1];
}

$entriesChanged = false;

class MyBibEntry extends BibEntry {
	var $tags;
	var $localFile;
	var $lastFileScan;

	function MyBibEntry() {
		parent::BibEntry()
		$this->tags = array();
		$this->lastFileScan = -1;
	}

	function setField($name, $value) {
		if (ENABLED_TAGS && $name == TAGS_FIELD) {
			$this->tags = array_unique(preg_split('/\s*,+\s*/', $value));
		}
		else {
			parent::setField($name, $value);
		}

		global $entriesChanged;
		$entriesChanged = true;
	}

	function hasTag($tag) {
		return in_array($tag, $this->tags);
	}

	function locateLocalFile() {
		global $entriesChanged;

		// If the directory hasn't changed since the last
		// scan, there is no point in attempting to find anything.
		// Therefore, just use whatever we currently got
		if (getLocalPdfDirMtime() < $this->lastFileScan) {
			return $this->localFile;
		}
		
		// Initiate a new scan.
		$this->lastFileScan = time();
		// First, check if we have a (still) valid local file
		// stored.
		if ($this->localFile) {
			if (file_exists($this->localFile)) {
				return $this->localFile;
			}
			$this->localFile = NULL;
			$entriesChanged = true;
		}

		// Check the 'file' field exported by JabRef
		if ($this->hasField('file')) {
			$fs = explode(':', $this->getField('file'));
			foreach ($fs as $f) {
				if (substr($f, -4) === '.pdf') {
					$localFile = LOCAL_PDF_DIR.'/'.$f;
					if (file_exists($f)) {
						$this->localFile = $localFile;
						$entriesChanged = true;
						return $localFile;
					}
				}
			}
		}
		
		// Check for the existence of a file "$key.pdf"
		$key = $this->getKey();
		$name = LOCAL_PDF_DIR.'/'.$key.'.pdf';
		if (file_exists($name)) {
			$this->localFile = $name;
			$entriesChanged = true;
			return $name;
		}
		// Locate the file via globbing for "$key *.pdf"
		$pattern = LOCAL_PDF_DIR.'/'.$key.' *.pdf';
		$results = glob($pattern);
		if ($results) {
			$this->localFile = $results[0];
			$entriesChanged = true;
		}

		return $this->localFile;
	}

}

class MyBibEntryDisplay extends BibEntryDisplay {
	function MyBibEntryDisplay($bib = null) {
		parent::BibEntryDisplay($bib);
	}

	function myDisplay() {
		$subtitle = '<div class="bibentry-by">by '.$this->bib->getFormattedAuthorsImproved().'</div>';

		$abstract = '';
		if ($this->bib->hasField('abstract')) {
			$abstract = '<div class="bibentry-label">Abstract:</div><div class="bibentry-abstract">'.$this->bib->getAbstract().'</div>';
		}
		$download = '';
		$link = createLocalFileLink($this->bib);
		if ($link) {
			$download .= '<div class="bibentry-document-link-local"><a href="'.$link.'">View PDF</a></div>';
		}
		if ($this->bib->hasField('pdfurl')) {
			$pdfUrl = $this->bib->getField('pdfurl');
			$hostname = extractHost($pdfUrl);
			$download .= '<div class="bibentry-document-link"><a href="'.$pdfUrl.'"> View PDF ';
			if ($hostname) {
				$download .= '@ ' . $hostname;
			}
			else {
				$download .= '(external)';
			}
			$download .= '</a></div>';
		}
		$reference= '<div class="bibentry-label">Reference:</div><div class="bibentry-reference">'.strip_tags(bib2html($this->bib)).'</div>';
		$bibtex = '<div class="bibentry-label">Bibtex Entry:</div>'.$this->bib->toEntryUnformatted().'';
		return $subtitle.$abstract.$download.$reference.$bibtex.$this->bib->toCoins();
	}

	function display() {
		echo '<div>';
		echo $this->myDisplay();
		echo '</div>';
	}

}

function createBibEntry() {
	$x = new MyBibEntry();
	return $x;
}

function createLocalFileLink($entry) {
	$localFile = $entry->locateLocalFile();
	if (!$localFile) {
		return null;
	}
	$name = basename($localFile);
	$key = $entry->getKey();
	return '../pdfs/'.$key.'/'.$name;
}

