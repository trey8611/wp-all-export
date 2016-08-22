<?php


/**
 * Class PMXE_XMLWriter
 */
class PMXE_XMLWriter extends XMLWriter
{

  /**
   * @var array
   */
  public $articles = array();

  /**
   * @param array $articles
   */
  public function writeArticle($articles = array() ){

      $article = array_shift($articles);

      if ( ! empty($articles) ){

          $keys = array();
          foreach ($articles as $a) {

              foreach ($a as $key => $value) {

                  if ( ! isset($article[$key]) ){
                      $article[$key] = array($value);
                  }
                  else{
                      $article[$key] = array($article[$key], $value);
                  }

                  if (!in_array($key, $keys)) $keys[] = $key;
              }
          }
      }

      if ( ! empty($article)) {
          foreach ($article as $key => $value) {
              if ( ! is_array($value) && strpos($value, '#delimiter#') !== FALSE) {
                  $article[$key] = explode('#delimiter#', $value);
              }
          }
      }

      $this->articles[] = $article;
  }

  /**
   * @param $ns
   * @param $element
   * @param $uri
   * @param $value
   * @return bool
   */
  public function putElement($ns, $element, $uri, $value )
  {
      if ( in_array(XmlExportEngine::$exportOptions['xml_template_type'], array('custom', 'XmlGoogleMerchants')) ) return true;

      if (empty($ns))
      {
          return $this->writeElement( $element, $value );
      }
      else
      {
          return $this->writeElementNS( $ns, $element, $uri, $value );
      }
  }

  /**
   * @param $ns
   * @param $element
   * @param $uri
   * @return bool
   */
  public function beginElement($ns, $element, $uri)
  {
      if ( in_array(XmlExportEngine::$exportOptions['xml_template_type'], array('custom', 'XmlGoogleMerchants')) ) return true;

      if (empty($ns))
      {
          return $this->startElement( $element );
      }
      else
      {
          return $this->startElementNS( $ns, $element, $uri );
      }
  }

  /**
   * @return bool
   */
  public function closeElement(){

      if ( in_array(XmlExportEngine::$exportOptions['xml_template_type'], array('custom', 'XmlGoogleMerchants')) ) return true;

      return $this->endElement();
  }

    /**
     * @param $value
     * @param $element_name
     *
     * @return bool
     */
  public function writeData($value, $element_name)
  {
      if ( in_array(XmlExportEngine::$exportOptions['xml_template_type'], array('custom', 'XmlGoogleMerchants')) ) return true;

      $cdataStrategyFactory = new CdataStrategyFactory();

      if(!isset(XmlExportEngine::$exportOptions['custom_xml_cdata_logic'])) {
          XmlExportEngine::$exportOptions['custom_xml_cdata_logic'] = 'auto';
      }
      $cdataStrategy = $cdataStrategyFactory->create_strategy(XmlExportEngine::$exportOptions['custom_xml_cdata_logic']);
      $is_wrap_into_cdata = $cdataStrategy->should_cdata_be_applied($value);

      $wrap_value_into_cdata = apply_filters('wp_all_export_is_wrap_value_into_cdata', $is_wrap_into_cdata, $value, $element_name);

      if ( $wrap_value_into_cdata === false ) {
          $this->text($value);
      }
      else {
          if(XmlExportEngine::$is_preview && XmlExportEngine::$exportOptions['show_cdata_in_preview']) {
              $this->text('CDATABEGIN'.$value.'CDATACLOSE');
          } else {
              $this->writeCdata($value);
          }
      }
  }

  /**
   * @return mixed|string
   */
  public function wpae_flush()
  {
      if ( ! in_array(XmlExportEngine::$exportOptions['xml_template_type'], array('custom', 'XmlGoogleMerchants')) ) return $this->flush( true );

      $xml = '';
      foreach ($this->articles as $article) {
          $founded_values = array_keys($article);
          $node_tpl = XmlExportEngine::$exportOptions['custom_xml_template_loop'];
          // clean up XPaths for not founded values
          preg_match_all("%(\{[^\}\{]*\})%", $node_tpl, $matches);
          $xpaths = array_unique($matches[0]);
          if ( ! empty($xpaths)){
              foreach ($xpaths as $xpath) {
                  if ( ! in_array(preg_replace("%[\{\}]%", "", $xpath), $founded_values)){
                      $node_tpl = str_replace($xpath, "", $node_tpl);
                  }
              }
          }
          foreach ($article as $key => $value) {
              switch ($key) {
                  case 'id':
                      $node_tpl = str_replace('{ID}', '{'.$value.'}', $node_tpl);
                      break;
                  default:
                      // replace [ and ]
                      $v = str_replace(']', 'CLOSEBRAKET', str_replace('[', 'OPENBRAKET', $value));
                      // replace { and }
                      $v = str_replace('}', 'CLOSECURVE', str_replace('{', 'OPENCURVE', $v));

                      if ( !empty($v) && is_array($v) ){
                          $delimiter = uniqid();
                          $v = "[explode('".$delimiter."', '". implode($delimiter, $v) ."')]";
                      }
                      else{
                          $v = '{' . $v . '}';
                      }
                      $node_tpl = str_replace('{'.$key.'}', $v, $node_tpl);
                      break;
              }
          }

          $xml .= $node_tpl;
      }

      $this->articles = array();

      return self::preprocess_xml( $xml );
  }

  /**
   * @param string $xml
   * @return mixed|string
   */
  public static function preprocess_xml($xml = '' ){

      $xml = str_replace('<![CDATA[', 'DOMCdataSection', $xml);

      preg_match_all("%(\[[^\]\[]*\])%", $xml, $matches);
      $snipets = empty($matches) ? array() : array_unique($matches[0]);

      $simple_snipets = array();
      preg_match_all("%(\{[^\}\{]*\})%", $xml, $matches);
      $xpaths = array_unique($matches[0]);
      if ( ! empty($xpaths)){
          foreach ($xpaths as $xpath) {
              if ( ! in_array($xpath, $snipets)) $simple_snipets[] = $xpath;
          }
      }

      if ( ! empty($snipets) ){
          foreach ($snipets as $snipet) {
              // function founded
              if ( preg_match("%\w+\(.*\)%", $snipet) ){
                  $filtered = trim(trim(trim($snipet, "]"), "["));
                  $filtered = preg_replace("%[\{\}]%", "\"", $filtered);
                  $filtered = str_replace('CLOSEBRAKET', ']', str_replace('OPENBRAKET', '[', $filtered));
                  $filtered = str_replace('CLOSECURVE', '}', str_replace('OPENCURVE', '{', $filtered));

                  $values = eval("return " . $filtered . ";");
                  if ( is_array($values) && count($values) == 1 ) $values = $values[0];
                  $v = '';
                  if ( is_array($values) ){
                      $tag = false;

                      preg_match_all("%(<[\w]+[\s|>]{1})". preg_quote($snipet) ."%", $xml, $matches);

                      if ( ! empty($matches[1]) ){
                          $tag = array_shift($matches[1]);
                      }
                      if ( empty($tag)) $tag = "<item>";

                      foreach ($values as $number => $value){
                          $v .= $tag . self::maybe_cdata($value) . str_replace("<", "</", $tag) . "\n";
                      }

                      $xml = str_replace($tag . $snipet . str_replace("<", "</", $tag), $v, $xml);
                  }
                  else
                  {
                      $xml = str_replace($snipet, self::maybe_cdata($values), $xml);
                  }
              }
          }
      }

      if ( ! empty($simple_snipets) ){
          foreach ($simple_snipets as $snipet) {
              $filtered = preg_replace("%[\{\}]%", "", $snipet);
              $filtered = str_replace('CLOSEBRAKET', ']', str_replace('OPENBRAKET', '[', $filtered));
              $filtered = str_replace('CLOSECURVE', '}', str_replace('OPENCURVE', '{', $filtered));
              $xml = str_replace($snipet, self::maybe_cdata($filtered), $xml);
          }
      }

      $xml = str_replace('DOMCdataSection', '<![CDATA[', $xml);

      return $xml;
  }

    /**
     * @param $v
     * @return string
     */
    public static function maybe_cdata($v ){
      $is_wrap_into_cdata = false;
      switch (XmlExportEngine::$exportOptions['custom_xml_cdata_logic'])
      {
          case 'auto':
              if ( ! empty($v) and  preg_match('%[&<>]+%', $v)){
                  $is_wrap_into_cdata = true;
              }
              break;

          case 'all':
              $is_wrap_into_cdata = true;
              break;

          case 'never':
              $is_wrap_into_cdata = false;
              break;
      }
      return $is_wrap_into_cdata ? "<![CDATA[" . $v . "]]>" : $v ;
    }
} 