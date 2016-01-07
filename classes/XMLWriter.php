<?php

class PMXE_XMLWriter extends XMLWriter
{

	public function putElement( $ns, $element, $uri, $value )
	{
		if (empty($ns))
		{
			return $this->writeElement( $element, $value );
		}
		else
		{
			return $this->writeElementNS( $ns, $element, $uri, $value );
		}
	}

	public function beginElement($ns, $element, $uri)
	{
		if (empty($ns))
		{
			return $this->startElement( $element );
		}
		else
		{
			return $this->startElementNS( $ns, $element, $uri );
		}
	}

} 