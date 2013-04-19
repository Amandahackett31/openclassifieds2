<?php defined('SYSPATH') or die('No direct script access.');
/**
 * description...
 *
 * @author		Slobodan <slobodan.josifovic@gmail.com>
 * @package		OC
 * @copyright	(c) 2009-2011 Open Classifieds Team
 * @license		GPL v3
 * *
 */
class Model_Order extends ORM {


	/**
	 * Table name to use
	 *
	 * @access	protected
	 * @var		string	$_table_name default [singular model name]
	 */
	protected $_table_name = 'orders';

	/**
	 * Column to use as primary key
	 *
	 * @access	protected
	 * @var		string	$_primary_key default [id]
	 */
	protected $_primary_key = 'id_order';

	/**
	 * Status constants
	 */
    const STATUS_CREATED        = 0;   // just created
    const STATUS_PAID           = 1;   // paid!
    const STATUS_REFUSED        = 5;   //tried to paid but not succeed
    const STATUS_REFUND         = 99;  //we refunded the money

    /**
     * @var  array  Available statuses array
     */
    public static $statuses = array(
        self::STATUS_CREATED      =>  'Created',
        self::STATUS_PAID         =>  'Paid',
        self::STATUS_REFUSED      =>  'Refused',
        self::STATUS_REFUND       =>  'Refund',
    );

    /**
     * confirm payment for order
     *
     * @param string    $id_order [unique indentifier of order]
     * @param int       $id_user  [unique indentifier of user] 
     */
    public function confirm_payment($id_order, $moderation)
    {
        $orders = new self;

        $orders->where('id_order','=',$id_order)
                         ->where('status','=', 0)
                         ->limit(1)->find();

        $id_ad = $orders->id_ad;

        $product_find = new Model_Ad();
        $product_find = $product_find->where('id_ad', '=', $id_ad)->limit(1)->find();
        $categ = new Model_Category($product_find->id_category);

        
        // update orders
        if($orders->loaded())
        {
            $orders->status = 1;
            $orders->pay_date = Date::unix2mysql(time());
            
            try {
                $orders->save();
            } catch (Exception $e) {
                echo $e;  
            }
        }

        // update product 
        if($orders->id_product == Paypal::category_product)
        {

            if($moderation == 2)
            {
                $product_find->published = Date::unix2mysql(time());
                $product_find->status = 1;

                try {
                    $product_find->save();
                } catch (Exception $e) {
                    echo $e;
                }
            }
            else if($moderation == 5)
            {
                $product_find->published = Date::unix2mysql(time());
                
                try 
                {
                    $product_find->save();      
                } catch (Exception $e) {
                   
                }   
            }
        }
        elseif($orders->id_product == Paypal::to_top)
        {
            $product_find->published = Date::unix2mysql(time());
            try {
                $product_find->save();
            } catch (Exception $e) {
                echo $e;
            }
        }
        elseif ($orders->id_product == Paypal::to_featured)
        {
            $product_find->featured = Date::unix2mysql(time() + (core::config('general.featured_timer') * 24 * 60 * 60));
            try {
                $product_find->save();
            } catch (Exception $e) {
                echo $e;
            }
        }
    }

    /**
	 * [set_new_order] Creates new order with given parameters, and gets newlly created id_order
	 * @param  [array] $ord_data array of necessary parameters to create order
	 * @return [int] self order id
	 */
	public static function set_new_order($ord_data)
	{

		//create order		
		$order = new self;

		$order->id_user       = $ord_data['id_user'];
		$order->id_ad         = $ord_data['id_ad'];
		$order->id_product    = $ord_data['id_product'];
		$order->paymethod     = $ord_data['paymethod'];
		$order->currency      = $ord_data['currency'];
		$order->amount        = $ord_data['amount'];
        $order->description   = $ord_data['description'];

		try 
		{
			$order->save();
		} 
		catch (Exception $e){
			Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e));
		} 

		// find correct order to make paypal invoice 
		$order_id = new Model_Order();
		$order_id = $order_id->where('id_ad','=',$ord_data['id_ad'])
							 ->where('status','=',0)
							 ->where('id_user','=',$ord_data['id_user'])
							 ->where('id_product', '=', $ord_data['id_product'])
							 ->order_by('id_order', 'desc')
							 ->limit(1)->find();
		$order_id = $order_id->id_order; 

		return $order_id;
	}

    /**
     * [make_new_order] Process data related to new advert and makes call to payment system. 
     * Controlls price of a product and calls function for seting new order to create new order in DB 
     * @param  [array] $data        [Array with data related to advert]
     * @param  [int] $usr           [user id]
     * @param  [string] $seotitle   [seotitle of advertisement]
     * @return [view]               [Redirect to payment or back to home if price is 0]
     */
    public function make_new_order($data, $usr, $seotitle)
    {
        $category   = new Model_Category();
        $cat        = $category->where('id_category', '=', $data['cat'])->limit(1)->find();

        // check category price, if 0 check parent
        if($cat->price == 0)
        {
            $parent     = $cat->id_category_parent;
            $cat_parent = new Model_Category();
            $cat_parent = $cat_parent->where('id_category', '=', $parent)->limit(1)->find();

            if($cat_parent->price == 0) // @TODO add case of moderation + payment (moderation = 3)
            {
                Alert::set(Alert::SUCCESS, __('Advertisement is scheduled to be posted, you will be notified when becomes published. Thanks!'));
                return $order_id = NULL;
            }
            else
            {
                $amount = $cat_parent->price;
            }
        }
        else
        {
            $amount = $cat->price;
        }
        // make order 
        $payer_id = $usr; 
        $id_product = Paypal::category_product;

        $ad = new Model_Ad();
        $ad = $ad->where('seotitle', '=', $seotitle)->limit(1)->find();

        $ord_data = array('id_user'     => $payer_id,
                          'id_ad'       => $ad->id_ad,
                          'id_product'  => $id_product,
                          'paymethod'   => 'paypal', // @TODO - to strict
                          'currency'    => core::config('paypal.paypal_currency'),
                          'amount'      => $amount,
                          'description' => $cat->seoname);

        $order_id = new self; // create order , and returns order id
        $order_id = $this->set_new_order($ord_data);

        return $order_id;
    }
}