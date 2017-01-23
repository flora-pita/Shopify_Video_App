<?php
class Order_model extends Master_model
{
  protected $_tablename = 'orderlist';
  private $_total_count = 0;
  
  function __construct() {
      parent::__construct();
  }

  public function getTotalCount(){ return $this->_total_count; }
  
  
  /**
  * Get the list of product/ varints
  * array(
  *     'customer_name' => '',       // String
  *     'sort' => '',                   // String "{column} {order}"
  *     'page_number' => '',            // Int, default : 0
  *     'page_size' => '',              // Int, default Confing['PAGE_SIZE'];
  *     'is_coupon' => '',              // Int, 0: all, 1: discount, 2: other / default : 0
  );
  */
  public function getList( $arrCondition )
  {
      $where = array();

      // Build the where clause
      $where['shop'] = $this->_shop;
      if( !empty( $arrCondition['customer_name'] ) ) $where["customer_name LIKE '%" . str_replace( "'", "\\'", $arrCondition['customer_name'] ) . "%'"] = '';
      if( !empty( $arrCondition['address'] ) ) $where["address LIKE '%" . str_replace( "'", "\\'", $arrCondition['address'] ) . "%'"] = '';

      $get_order = isset($arrCondition['data']) && $arrCondition['data']  ? ', data' : '';

      // Get the count of records
      foreach( $where as $key => $val )
      if( $val == '' )
          $this->db->where( $key );
      else
          $this->db->where( $key, $val );
      $query = $this->db->get( $this->_tablename);
      $this->_total_count = $query->num_rows();
      
      // Select fields
      $this->db->select( "id, order_id, order_name, created_at, customer_name, note, address, amount, financial_status" . $get_order );
      
      // Sort
      if( isset( $arrCondition['sort'] ) ) $this->db->order_by( $arrCondition['sort'] );
      $this->db->order_by( 'created_at', 'DESC' );

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
      $query = $this->db->get( $this->_tablename );
      
      return $query;
  }
  
  // Get the lastest order date
  public function getLastOrderDate()
  {
      $return = '';
      
      $this->db->select( 'created_at' );
      $this->db->order_by( 'created_at DESC' );
      $this->db->limit( 1 );
      $this->db->where( 'shop', $this->_shop );
      
      $query = $this->db->get( $this->_tablename );
      
      if( $query->num_rows() > 0 )
      {
          $res = $query->result();
          
          $return = $res[0]->created_at;
      }
      
      return $return;
  }
  
  /**
  * Add order and check whether it's exist already
  * 
  * @param mixed $order
  */
  public function add( $order )
  {
    // Check the order is exist already
    $query = parent::getList('order_id = \'' . $order->id . '\'' );
    if( $query->num_rows() > 0 ) return false;

    // Load Models
    $CI =& get_instance();
    $CI->load->model( 'Product_model' );
    $CI->load->model( 'Result_model' );
    
    // Get the product list with offer
    $arrProduct = $CI->Product_model->getProductOfferList( 'checkout' );
    
    // Check order lines and get offer usage
    if( is_array( $order->line_items) )
    foreach( $order->line_items as $line_item )
    {
      if( isset( $arrProduct['_' . $line_item->variant_id] ) )
      {
        $CI->Result_model->addPurchase( $arrProduct['_' . $line_item->variant_id]['offer_id'], $order->id, $line_item->product_id, $line_item->price * $line_item->quantity, date( $this->config->item('CONST_DATE_FORMAT'), strtotime( $order->created_at )) );
      }
    }
    
    // Get Order Information      
    $objAddress = isset( $order->shipping_address ) ? $order->shipping_address : $order->billing_address;
    $address = '';
    if( $objAddress->address1 != '' ) $address .= ', ' . $objAddress->address1;
    if( $objAddress->address2 != '' ) $address .= ', ' . $objAddress->address2;
    if( $objAddress->city != '' ) $address .= ', ' . $objAddress->city;
    if( $objAddress->province != '' ) $address .= ', ' . $objAddress->province;
    if( $objAddress->country != '' ) $address .= ', ' . $objAddress->country;
    if( $objAddress->zip != '' ) $address .= ', ' . $objAddress->zip;
    if( $address != '' ) $address = substr( $address, 2 );
    
    // Insert data
    $data = array(
        'order_id' => $order->id,
        'customer_name' => $order->customer->first_name . ' ' . $order->customer->last_name,
        'order_name' => $order->name,
        'created_at' => date( $this->config->item('CONST_DATE_FORMAT'), strtotime( $order->created_at )),
        'note' => isset( $order->note ) ? $order->note : '',
        'amount' => $order->total_price,
        'financial_status' => $order->financial_status,
        'company' => $objAddress->company,
        'address' => $address,
        'data' => base64_encode( json_encode( $order ) ),
    );

    parent::add( $data );
    
    return true;
  }
  
  public function cancel( $order )
  {
    $this->db->where( 'shop', $this->_shop );
    $this->db->where( 'order_id', $order->id );
    $this->db->update( $this->_tablename, array( 'fulfillment_status' => 'cancelled'));
  }
  
  // Get order object fron order_id
  public function getOrderObject( $order_name )
  {
    $query = parent::getList( 'order_name = \'' . $order_name . '\'' );
    if( $query->num_rows() > 0 )
    foreach( $query->result() as $row )
    {
      return json_decode( base64_decode($row->data ));
    }
    
    return '';
  }
  
  // Get the line item_item Id from sku
  public function getLineItemId( $order, $sku )
  {
    foreach( $order->line_items as $line_item )
    {
      if( $line_item->sku == $sku ) return $line_item->id;
    }
    
    return '';
  }
  // ********************** //
}  
?>
