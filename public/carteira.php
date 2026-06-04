# Massango PHP Social Platform Wallet
## Requirements
* Authentication via includes/auth.php.
* Redirect to login if not logged in.
## Pages
* Displays (1) current balance in MZN from users.balance.
* Displays (2) total earned from sales where seller_id=current user AND status=completed.
* Displays (3) total spent from sales where buyer_id=current user AND status=completed.
* Display last 20 transactions unified from sales table showing date, type Compra or Venda, content_type, amount, status badge colored green/red/yellow.