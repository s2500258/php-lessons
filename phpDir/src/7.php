<?php include "functions.php"; ?>
<?php include "includes/header.php";?>
    

	<section class="content">

		<aside class="col-xs-4">

		<?php Navigation();?>
			
			
		</aside><!--SIDEBAR-->


	<article class="main-content col-xs-8">
	
	
	
	<?php  

	/*  Step 1 - Create a database in PHPmyadmin

		Step 2 - Create a table like the one from the lecture

		Step 3 - Insert some Data

		Step 4 - Connect to Database and read data

*/

	$host = "db";
	$dbname = "lionDB";
	$username = "lionUser";
	$password = "lionPass";
	$conn = new mysqli($host, $username, $password, $dbname);

	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	} else {
		echo "Success";
	}

	echo "<br>";

	$sql = "SELECT * from users;";
	$result = $conn->query($sql);
	while ($row = $result->fetch_assoc()){
		echo "ID: " . $row["id"] . " - Name: " . $row["name"] . "<br>";
	}

	$conn->close();
	
	?>





</article><!--MAIN CONTENT-->

<?php include "includes/footer.php"; ?>
