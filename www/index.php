<html>
<head>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <script
        src="https://code.jquery.com/jquery-3.3.1.min.js"
        integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
        crossorigin="anonymous"></script>


    <style>
        .row-section, .value-section {
            padding: 4px 4px;
            border-bottom: 1px solid #999;
            word-wrap: break-word;
        }

        .value-section:nth-child(odd) {
            background: #dddddd;
        }


        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
        }
        .loading-bar {
            display: inline-block;
            width: 4px;
            height: 18px;
            border-radius: 4px;
            animation: loading 1s ease-in-out infinite;
        }
        .loading-bar:nth-child(1) {
            background-color: #3498db;
            animation-delay: 0s;
        }
        .loading-bar:nth-child(2) {
            background-color: #c0392b;
            animation-delay: 0.09s;
        }
        .loading-bar:nth-child(3) {
            background-color: #f1c40f;
            animation-delay: .18s;
        }
        .loading-bar:nth-child(4) {
            background-color: #27ae60;
            animation-delay: .27s;
        }

        @keyframes loading {
            0% {
                transform: scale(1);
            }
            20% {
                transform: scale(1, 2.2);
            }
            40% {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Heroku Worker PDF upload Demo</h1>
    <div class="col-lg-12">
        <p class="">Insert the name of the file to upload to S3. Link will appear below (with the name specified) when it is processed and ready on S3.</p>
    </div>




    <div class="col-lg-6">
        <form id="target">
            <div class="form-group">
                <label for="filename">File Name</label>
                <input type="text" class="form-control" id="filename" placeholder="Filename">
            </div>

            <div class="form-group">
                <label for="content">Content</label>
                <textarea class="form-control" id="content" placeholder="Sample Content"></textarea>
            </div>

            <button type="submit"  class="btn btn-default">Submit</button>
        </form>
    </div>



    <div class="col-lg-12" style="padding-top: 20px;">
        <h3>Uploaded Files:</h3>

        <div class="col-lg-12 table-section" >
            <div class="row row-section" style="background: #f0ad4e;">
                <div class="col-lg-3"><strong>Name</strong></div>
                <div class="col-lg-9"><strong>Link</strong></div>
            </div>

        </div>
    </div>
</div>

<script>
    $( "#target" ).submit(function( event ) {
        event.preventDefault();
        var isLoading = [];
        var fileName = $("#filename").val();
        var className = fileName.replace(/[^a-z0-9\s]/gi, '').replace(/[_\s]/g, '-');
        var content = $("#content").val();
        $.post( "https://php-bg.herokuapp.com/build-file.php", { filename: fileName, content: content })
            .done(function( data ) {
                if (data != null && data[0] == "success") {
                    $(".table-section").append('<div  class="row value-section ' + className +'">' +
                        '<div class="col-lg-3">' + fileName + '</div>' +
                        '<div class="col-lg-9 link"><div class="loading"><span>Processing...  </span><div class="loading-bar"></div><div class="loading-bar"></div><div class="loading-bar"></div><div class="loading-bar"></div></div></div>' +
                        '</div>');

                    isLoading[className] = true;


                    var iid = setInterval(
                        function(){
                            $.ajax({
                                url: "https://php-bg.herokuapp.com/check-status.php",
                                data: { filename: fileName},
                                type: "GET",
                                cache: false,
                                statusCode: {
                                    404: function() {

                                    },
                                    200: function(data) {
                                        if (isLoading[className]===true){
                                            if (data != null && data[0] != "") {
                                                isLoading[className] = false;
                                                $("." + className + " .link").html('<a href="' + data[0] + '">' + data[0] + '</a>');
                                                clearInterval(iid);
                                            }
                                        }
                                    }
                                }
                            });
                        },
                        2000
                    );

                }

            });

    });

    $(function() {

    });

</script>

</body>
</html>