<?php 
/**
* 
*/
class checkout extends CI_controller
{
	
	function __construct()
	{
		parent::__construct();
		$this->load->library('tank_auth');
		$this->load->library('cart');
		$this->load->library('session');
		$this->load->helper('url');
		$this->load->helper('html');
		$this->load->model('database');
	}

	function index()
	{
		$this->login();
	}

	function GenerateHeader(&$data)
	{
		//Login Info
		$data['user_id'] = 0;
		$data['user_name'] = null;

		if($this->tank_auth->is_logged_in())
		{
			$data['user_id'] 	= $this->tank_auth->get_user_id();
			$data['user_name'] 	= $this->tank_auth->get_username();
		}

		//Cart Info
		$data['num_items'] = $this->cart->total_items();
		$data['total_price'] = $this->cart->total();

		//Game search Links
		$data['supported_games'] = $this->database->GetAllSuportedGames();		
	}

	function display($page, $data)
	{
		$this->GenerateHeader($data);

		//Show header
		$this->load->view('header', $data);

		//Show body		
		switch ($page)
		{
			case 'address':
				$this->load->view('view_address', $data);
			break;
			case 'review':
				$this->load->view('view_review_order', $data);
			break;
			default:
				show_404();
			break;		
		}		

		//Show footer
		$this->load->view('footer', $data);
	}

	function login()
	{
		if(!$this->tank_auth->is_logged_in())
			redirect('auth');
		else
			redirect('checkout/address');
	}

	function address()
	{
		$userid = $this->tank_auth->get_user_id();
		
		$result = $this->database->GetAddressesForUser($userid);		
		$data['addresses'] = $result;
		$this->display('address',$data);
	}

	function review()
	{
		foreach ($this->cart->contents() as $items)
		{			
			$prod_id = $items['id'];
			$product = $this->database->GetProductById($prod_id);
			$data['products'][$prod_id] = $product;
		}

		$data['address'] = $this->session->userdata('shipping_address');
		
		$this->display('review', $data);
	}

	//Store the address in session as user can refresh the page
	//Also makes sure that address passed is valid for current signed-in user
	function save_address()
	{
		$address_id = $this->input->post('address_id');		
		$address = $this->database->GetAddressById($address_id);

		//We also need to make sure address belongs to the currently signed-in user		
		$current_users_addresses = $this->database->GetAddressesForUser($this->tank_auth->get_user_id());
		$address_valid = FALSE;
		foreach ($current_users_addresses as $key => $address)
		{
			if($address['address_id'] == $address_id)
			{
				$address_valid = TRUE;
				break;
			}			
		}

		if($address_valid)
		{
			//We need to be here to show the review page, else we go again to address page
			//to get correct address
			$this->session->set_userdata('shipping_address',$address);
			redirect('checkout/review');
		}

		redirect('checkout/address');
	}

	function payment()
	{
		if($this->input->post('address_id') != (string)FALSE)
			$address_id = $this->input->post('address_id');
		else
		{			
			redirect('checkout/address');
		}
			
		
		$order = array
				(
					'user_id'	=>	$this->tank_auth->get_user_id(),
					'address_id' => $address_id,
				);

		$this->database->AddOrder($order);

		$num_orders = $this->database->GetNumOrders();

		foreach ($this->cart->contents() as $item)
		{
			$order_item = array
						(
							'order_id' 		=> $num_orders,
							'product_id'	=> $item['id'],
							'count'			=> $item['qty'],
							'size'			=> $item['options']['Size']
						);

			//Update product info
			$size = $item['options']['Size'];
			$size = 'product_count_'.strtolower($size);			
			$product = $this->database->GetProductById($item['id']);			
			$product['product_qty_sold'] += $item['qty'];
			$product[$size] -= $item['qty'];			

			//Update database
			$this->database->ModifyProduct($product);
			$this->database->AddOrderItem($order_item);
		}

		//Destroy the cart now
		$this->cart->destroy();
		$this->session->unset_userdata('discount_coupon');
		redirect('checkout/success');
	}

	function success()
	{
		$this->ci->session->unset_userdata('shipping_address');
		echo "Your order has been placed, Cheers !!";
	}
}
?>