(function ($) {
  $(document).ready(function () {
    const $parent = $("#wpwand-bulk-post-generator");

    // bacek functionality
    $parent.find(".wpwand-pgs-back-button").on("click", function (e) {
      e.preventDefault();
      let target = $(this).data("target");

      $parent.find(".step-content").removeClass("active");
      $parent.find(".wpwand-pgs-header .step").removeClass("active");

      $parent.find("#" + target).addClass("active");
      $parent.find("[data-id=" + target + "]").addClass("active");
    });

    // approve functionality
    $(".wpwand-pgdc-approve-button").on("click", function (e) {
      e.preventDefault(); // Prevent the default behavior (navigating) until the user confirms

      Swal.fire({
        title: "Are you sure you want to approve this??",
        text: "After your confirmation, it will be added as a Draft post in your blog.",
        showDenyButton: true,
        confirmButtonText: "Confirm",
        denyButtonText: `Cancel`,
        customClass: {
          popup: "wpwand-swal-alert wpwand-swal-alert-approve-pgc",
          // icon: 'wpwand-swal-alert',
        },
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: "Successfully Added to Post!",
            icon: "success",
            showConfirmButton: false,
            timer: 1500,
            customClass: {
              popup: "wpwand-swal-alert wpwand-swal-alert-approve-pgc",
              // icon: 'wpwand-swal-alert',
            },
          }).then(() => {
            window.location.href = $(this).attr("href");
          });
        }
      });
    });
    $("#wpwand-bulk-post-generator .delete").on("click", function (e) {
      e.preventDefault(); // Prevent the default behavior (navigating) until the user confirms

      wpwand_delete_alert(this, "Are you sure you want to delete?");
    });

    $(".wpwand-pg-form").on("submit", function (e) {
      e.preventDefault();

      const $this = $(this);
      const is_custom_form = $this.is("#wpwand-pgf-custom");

      if (is_custom_form) {
        // Get the text from the textarea
        $parent
          .find("#wpwand-post-content-generate-form .wpwand-pcgf-title-list")
          .html("");

        // Split the text into an array of lines using the newline character as the delimiter
        var text = $("#titles").val();
        var linesArray = text.split("\n");
        linesArray = linesArray.filter(function (line) {
          return line.trim() !== ""; // Remove lines that are empty or contain only whitespace
        });

        // You can iterate through the linesArray and perform further processing as needed
        let count = 0;
        $.each(linesArray, function (index, line) {
          count++;
          // Do something with each line
          console.log("Line " + (index + 1) + ": " + line);

          $parent
            .find("#wpwand-post-content-generate-form .wpwand-pcgf-title-list")
            .append(
              `
                    <div class="wpwand-pcgf-heading-item">
                        <div class="wpwand-pcgf-heading-content">
                            <input type="checkbox" id="selected_headings-` +
                index +
                1 +
                `" name="selected_headings[]" value="` +
                line +
                `" checked> 
                            <label for="selected_headings-` +
                index +
                1 +
                `">` +
                line +
                `</label>
                        </div>
                    </div>
                    `
            );
        });

        $parent.find(".wpwand-pg-info-topic").parent().hide();
        $parent.find(".wpwand-pg-info-count").html(count);

        $this.find("button[type=submit]").css("opacity", "1");
        $parent.find(".step-content").removeClass("active");
        $parent.find(".wpwand-pgs-header .step").removeClass("active");

        $parent.find("#step-2").addClass("active");
        $parent.find("[data-id=step-2]").addClass("active");

        return;
      } else {
        const topic = $this.find("input[name=topic]").val();
        const count = $this.find("input[name=post_count]").val();

        $this.find("button[type=submit]").css("opacity", ".6");
        $parent.find(".wpwand-pg-info-topic").html(topic);
        $parent.find(".wpwand-pg-info-count").html(count);
        // Use $.post instead of $.ajax for simpler code
        $.post({
          url: wpwand_glb.ajax_url,
          data: {
            action: "wpwand_post_generator",
            topic,
            count,
          },
          success: function (response) {
            $this.find("button[type=submit]").css("opacity", "1");
            $parent.find(".step-content").removeClass("active");
            $parent.find(".wpwand-pgs-header .step").removeClass("active");

            $parent.find("#step-2").addClass("active");
            $parent.find("[data-id=step-2]").addClass("active");

            $parent
              .find(
                "#wpwand-post-content-generate-form .wpwand-pcgf-title-list"
              )
              .html(response);
          },
        });
      }
    });

    $parent
      .find("#wpwand-post-content-generate-form button[type=button]")
      .on("click", function (e) {
        e.preventDefault();

        $parent.find(".step-content").removeClass("active");
        $parent.find(".wpwand-pgs-header .step").removeClass("active");

        $parent.find("#step-3").addClass("active");
        $parent.find("[data-id=step-3]").addClass("active");

        let total_selected = $parent.find(
          'input[name="selected_headings[]"]:checked'
        ).length;
        $parent.find(".wpwand-pg-info-total-selected").html(total_selected);
      });

    $parent.find("#step-3 button.start-generation").on("click", function (e) {
      e.preventDefault();
      $parent.find("#wpwand-post-content-generate-form").submit();
    });

    $parent
      .find("#wpwand-post-content-generate-form")
      .on("submit", function (e) {
        e.preventDefault();

        const $this = $(this);

        const selected_title = [];
        const keyword = $this.find("#keyword").val();
        const tone = $this.find("#tone").val();
        const toc_include = $this.find("#toc_include").is(":checked");
        const faq_include = $this.find("#faq_include").is(":checked");
        $this
          .find('input[name="selected_headings[]"]:checked')
          .siblings("label")
          .each(function () {
            selected_title.push($(this).text());
          });

        $.post({
          url: wpwand_glb.ajax_url,
          data: {
            action: "wpwand_post_content_generator",
            selected_title,
            keyword,
            tone,
            toc_include,
            faq_include,
          },
          success: function (response) {
            console.log(response);
            // $this.find('button[type=submit]').css('opacity', '1')
            window.location =
              wpwand_glb.admin_url +
              "/admin.php?page=wpwand-post-generator&generated-post";
          },
        });
      });

    function wpwand_delete_alert($this, title) {
      Swal.fire({
        title: title,
        showDenyButton: true,
        confirmButtonText: "Yes",
        cancelButtonText: `No`,
        customClass: {
          popup: "wpwand-swal-alert wpwand-swal-alert-approve-pgc",
        },
      }).then((result) => {
        if (result.isConfirmed) {
          Swal.fire({
            title: "Successfully Deleted!",
            icon: "success",
            showConfirmButton: false,
            timer: 1500,
            customClass: {
              popup: "wpwand-swal-alert wpwand-swal-alert-approve-pgc",
              // icon: 'wpwand-swal-alert',
            },
          }).then(() => {
            window.location.href = $($this).attr("href");
          });
        }
      });
    }

    function wpwandCheckProgress(id, $this) {
      $.post({
        url: wpwand_glb.ajax_url,
        data: {
          action: "wpwand_post_generation_progress",
          id,
        },
        success: function (response) {
          if (response == "complete") {
            $this.removeClass("pending");
            $this.removeClass("failed");
            $this.addClass("done");
            $this.text("Complete");
          }
          if (response == "failed") {
            $this.removeClass("pending");
            $this.addClass("failed");
            $this.text("Failed");
          }
          if (response == "in-progress") {
            $this.removeClass("failed");
            $this.text("On Progress");
          }
          console.log(response);
        },
        complete: function () {
          // Call the function again after a certain interval
          if ($this.hasClass("pending") || $this.hasClass("failed")) {
            setTimeout(wpwandCheckProgress(id, $this), 3000); // Adjust the interval as needed
          }
          if (
            !$parent
              .find(".wpwand-pgdc-page table.wp-list-table td span.status")
              .hasClass("pending")
          ) {
            $parent.find(".wpwand-pgdc-page .wpwand-pgdc-header").hide();
          }
        },
        error: function (msg) {},
      });
    }

    // Call the function to start updating the current item

    $parent
      .find(".wpwand-pgdc-page table.wp-list-table td span.status")
      .each(function () {
        if (!$(this).hasClass("done")) {
          var id = $(this).closest("tr").data("id");
          wpwandCheckProgress(id, $(this));
        }
      });
  });
})(jQuery);
