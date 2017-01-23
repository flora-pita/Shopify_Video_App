<?php
class Product_model extends Master_model
{
  protected $_tablename = 'product';
  protected $_tablename_offer = 'offer';
  private $_total_count = 0;
  private $_arrProductKey = array();
  private $_arrOffer = array();
  
  function __construct() {
    parent::__construct();
    
    // Get the variant id list
    $query = parent::getList();
    
    if( $query->num_rows > 0 )
    foreach( $query->result() as $row )
    {
      $this->_arrProductKey[] = $row->variant_id;
    }
    
    // Get the Offer List
    // Load Models
    $CI =& get_instance();
    $CI->load->model( 'Offer_model' );
    
    $query = $CI->Offer_model->getList();
    if( $query->num_rows > 0 )
    foreach( $query->result() as $row )
    {
      $this->_arrOffer[ $row->id ] = array(
        'title' => $row->title,
        'url' => $row->url,
      );
    }
  }

  public function getTotalCount(){ return $this->_total_count; }
  
  /**
  * Get the list of product/ varints
  * array(
  *     'supplier' => '',   // String
  *     'name' => '',       // String
  *     'sku' => '',        // String
  *     'supplier_category' => '',   // String
  *     'price' => '',               // String "{from} {to}"
  *     'product_id' => '',             // String
  *     'variant_id' => '',             // String
  *     'sort' => '',                   // String "{column} {order}"
  *     'product_only' => '',           // Boolean true/false : default :false
  *     'page_number' => '',            // Int, default : 0
  *     'page_size' => '',              // Int, default Confing['PAGE_SIZE'];
  *     'is_imported' => '',            // Int, 0: all, 1: published, 2: not-published / default : 0
  *     'is_queue' => '',               // Int, 0: all, 1: queue, 2: not-queue, / default : 0
  *     'is_stock' => '',               // Int, 0: all, 1: in stock, 2: out of stock / default 0
  );
  */
  public function getList( $arrCondition )
  {
      $where = array( 'shop' => $this->_shop );
      
      // Build the where clause
      if( !empty( $arrCondition['name'] ) ) $where["title LIKE '%" . str_replace( "'", "\\'", $arrCondition['name'] ) . "%'"] = '';
      
      // Product only - Group by, Get total records
      if( isset( $arrCondition['page_number'] ) )
      {
        // Get the count of records
        foreach( $where as $key => $val )
        if( $val == '' )
            $this->db->where( $key );
        else
            $this->db->where( $key, $val );
        $query = $this->db->get( $this->_tablename);
        $this->_total_count = $query->num_rows();
      }
      
      // Sort
      if( isset( $arrCondition['sort'] ) ) $this->db->order_by( $arrCondition['sort'] );
      $this->db->order_by( 'product_id', 'DESC' );

      // Limit
      if( isset( $arrCondition['page_number'] ) )
      {
          $page_size = isset( $arrCondition['page_size'] ) ? $arrCondition['page_size'] : $this->config->item('PAGE_SIZE');
          $this->db->limit( $page_size, $arrCondition['page_number'] );
      }

      foreach( $where as $key => $val )
      if( $val == '' )
          $this->db->where( $key );
      else
          $this->db->where( $key, $val );
      $query = $this->db->get_where( $this->_tablename );
      
      $arrReturn = array();
      foreach( $query->result() as $row )
      {
        $row->offer_title_cart = $row->offer_id_cart == 0 ? '' : $this->_arrOffer[$row->offer_id_cart+0]['title'];
        $row->offer_title_checkout = $row->offer_id_checkout == 0 ? '' : $this->_arrOffer[$row->offer_id_checkout+0]['title'];
        
        $arrReturn[] = $row;
      }
      
      return $arrReturn;
  }
  
  // Get the product list with offer name
  public function getProductIdListWithOffer()
  {
    $return = array();
    
    $this->db->select( $this->_tablename . '.product_id, ' . $this->_tablename . '.title, ' . $this->_tablename . '.offer_id_cart, ' . $this->_tablename . '.offer_id_checkout' );
    $this->db->from( $this->_tablename );
    $this->db->group_by( $this->_tablename . '.product_id' );
    $this->db->where( $this->_tablename . '.shop', $this->_shop );
    
    $query = $this->db->get();
    
    if( $query->num_rows() > 0 )
    foreach( $query->result() as $row )
    {
      $offer_title = '';
      if( $row->offer_id_cart != 0 ) $offer_title .= $this->_arrOffer[$row->offer_id_cart]['title'] . '(cart)';
      if( $row->offer_id_checkout != 0 ) $offer_title .= ' ' . $this->_arrOffer[$row->offer_id_checkout]['title'] . '(checkout)';
      
      $return[ '_' . $row->product_id ] = array(
        'product_id' => $row->product_id,
        'title' => $row->title,
        'offer_id_cart' => $row->offer_id_cart,
        'offer_id_checkout' => $row->offer_id_checkout,
        'offer' => $offer_title,
      );
    }
    
    return $return;
  }
  
  // Get the Product list with offers ( INNER )
  public function getProductOfferList( $when = '' )
  {
    $return = array();
    
    $this->db->select( $this->_tablename . '.product_id, ' . $this->_tablename . '.variant_id, ' . $this->_tablename . '.offer_id_cart, ' . $this->_tablename . '.offer_id_checkout' );
    $this->db->from( $this->_tablename );
    $this->db->where( $this->_tablename . '.shop', $this->_shop );
    
    $query = $this->db->get();
    
    if( $query->num_rows() > 0 )
    foreach( $query->result() as $row )
    {
      $offer_id = $when == 'cart' ? $row->offer_id_cart : $row->offer_id_checkout;
      
      $return[ '_' . $row->variant_id ] = array(
        'product_id' => $row->product_id,
        'variant_id' => $row->variant_id,
        'offer_id' => $offer_id,
        'offer_url' => $offer_id == 0 ? '' : $this->_arrOffer[$offer_id]['url'],
      );
    }
    
    return $return;
  }
  
  // Get last updated date
  public function getLastUpdateDate()
  {
      $return = '';
      
      $this->db->select( 'updated_at' );
      $this->db->order_by( 'updated_at DESC' );
      $this->db->limit( 1 );
      $this->db->where( 'shop', $this->_shop );
      
      $query = $this->db->get( $this->_tablename );
      
      if( $query->num_rows() > 0 )
      {
          $res = $query->result();
          
          $return = $res[0]->updated_at;
      }
      
      return $return;
  }    
  
  // Add product to database
  public function addProduct( $product )
  {
    // Get the images as array
    $arrImage = array();
    foreach( $product->images as $item ) $arrImage[ $item->id ] = $item->src;
    
    foreach( $product->variants as $variant )
    {
      // Get image id
      $image_url = '';
      if( !empty($variant->image_id) ) $image_url = $arrImage[$variant->image_id];
      if( $image_url == '' && isset( $product->image->src ))
      {
        $image_url = $product->image->src;
      } 
      
      // Remove the existing product
      if( in_array( $variant->id, $this->_arrProductKey ))
      {
        return;
      }
      
      // Add the new variant
      $newProductInfo = array(
        'title' => $product->title,
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'handle' => $product->handle,
        'price' => $variant->price,
        'updated_at' => date( $this->config->item('CONST_DATE_FORMAT'), strtotime($variant->updated_at)),
        'image_url' => $image_url,
      );
      
      parent::add( $newProductInfo );
    }   
  }
  
  // Delete the product from product_id
  public function deleteProduct( $product_id )
  {
    $this->db->delete( $this->_tablename, array( 'product_id' => $product_id, 'shop' => $this->_shop ) );
    if( $this->db->affected_rows() > 0 )
        return true;
    else
        return false;
    
  }
  
  public function getImageFromHandle( $product_handle )
  {
    $return = '';
    
    $query = parent::getList( 'handle=\'' . $product_handle . '\'' );
    if( $query->num_rows() > 0 )
    {
      $result = $query->result();
      $return = array(
        'product_name' => $result[0]->title,
        'image_url' => $result[0]->image_url,
      );
    }
    
    return $return;
    
  }
  
  // Add offer to product
  public function addOffer( $arrProductId, $offer_id, $when = 'cart' )
  {
    foreach( $arrProductId as $product_id )
    {
      $data = array(
        'offer_id_' . $when => $offer_id
      );

      $this->db->where('shop', $this->_shop);
      $this->db->where('product_id', $product_id);
      $this->db->update( $this->_tablename, $data);        
    }  
  }
  
  // Delete offer from product
  public function delOffer( $offer_id )
  {
    $data = array(
      'offer_id_cart' => 0
    );

    $this->db->where('shop', $this->_shop);
    $this->db->where('offer_id_cart', $offer_id);
    $this->db->update( $this->_tablename, $data);        
    
    $data = array(
      'offer_id_checkout' => 0
    );

    $this->db->where('shop', $this->_shop);
    $this->db->where('offer_id_checkout', $offer_id);
    $this->db->update( $this->_tablename, $data);        
    
  }    

  // ********************** //
}  
?>
