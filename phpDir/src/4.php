<?php include "functions.php"; ?>
<?php include "includes/header.php";?>

	<section class="content">

	<aside class="col-xs-4">

		<?php Navigation();?>
			
		
	</aside><!--SIDEBAR-->


<article class="main-content col-xs-8">

	
	<?php  

/*  Step1: Define a function and make it return a calculation of 2 numbers

	Step 2: Make a function that passes parameters and call it using parameter values


 */
 echo "<h3>Step 1</h3>";
function calculateSum() {
    $a = 5;
    $b = 10;
    return $a + $b;
}
$result = calculateSum();
echo $result;

echo "<h3>Step 2</h3>";
function calculateSum2($a, $b) {
    $sum = $a + $b;
	return "Variable A = $a, Variable B = $b, result = $sum"; 
}

echo calculateSum2(8, 12);
	
?>





</article><!--MAIN CONTENT-->


<?php include "includes/footer.php"; ?>