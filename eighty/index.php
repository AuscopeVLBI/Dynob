<?php
echo '
<!DOCTYPE html>
<html>
    <head>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    </head>

    <body>
    ';

    $dfile = scandir("2022a");
    $dfile = array_diff($dfile, array('.', '..'));
    foreach ($dfile as $df){
        echo "        <li>$df</li>\n";
    }

    echo '<script type="text/javascript">
    $(document).ready(function(){

		function myFunction (aname,thisli){ 
			$.ajax({
				url: "ave.php", 
				method: "POST",
				data: { afile: aname },
				success: function(result){
				$(thisli).html(result);
				//window.location.href = "monitor.php?exp='.$_GET["exp"].'&snrr=1";
            }});
        }

        $( "li" ).each(function() {
            myFunction ($(this).text(),$(this));
        });
    });
</script>';

            
echo '    </body>
</html>';
?>
