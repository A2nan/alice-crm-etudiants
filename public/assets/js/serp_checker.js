"use strict";
function checkRank(element) {
  // Get the URL of the client's site
  let url = document.getElementById("url").innerHTML;

  // Enter the keyword and the url of the site to check (without the https://)
  let keyword = element.children[1].innerHTML;
  let googleApiKey = element.getAttribute('data-google-api-key');
  let googleCustomApiKey = element.getAttribute('data-google-custom-api-key');
  
  // Access the Google API.
  // Select the "Custom Search" option to create a custom search instance.
  // Obtain an API key for query authentication.
  // Specify the CX (custom search engine identifier) you want to use for the search.
  // Perform a search query by including the desired search term (specified after the "q" parameter in the query string).
  // Specify the output format of the search results by including "alt=json" in the query string.
  
  let api = "https://www.googleapis.com/customsearch/v1?key=" + googleApiKey + "&cx=" + googleCustomApiKey + "&q=" + keyword + "&alt=json";  

    let request = new XMLHttpRequest();
    request.open('GET', api, true);
    request.onload = function() {
      // If the request succeeds, we retrieve the data and parse them into json
      if (request.status >= 200 && request.status < 400) {
        let data = JSON.parse(request.responseText);

        // We collect the results of the query and store them in an array

        let items = data.items;
        let rank = 0;
        // We go through the table and we get the rank of the entered url
        // We add 1 to the rank because the table starts at 0
        for (let i = 0; i < items.length; i++) {
          if (items[i].link.indexOf(url) > -1) {
            rank = i + 1;
            break;
          }
        }
        let rankElement = document.querySelector("#rank"+element.dataset.keyword)
        if (rank > 0) {

          rankElement.innerHTML = "Le site est au rang : " + rank;
          // the API expects the SerpInfo identifier, not the keyword text
          saveRank(element.dataset.keyword, rank);

        } else {
          rankElement.innerHTML = "Le site n'est pas dans les 10 premiers résultats : position " + rank;
          // rankElement.innerHTML = element.dataset.keyword;
        }

      } else {
        console.log("error"+ element.dataset.keyword);

      }
    };
    request.onerror = function() {
      console.log("error");


    };
    request.send();
}

// function test(elements) {
//   elements.forEach(element => {
//     let rank = document.querySelector("#rank"+element.dataset.keyword)
//       rank.innerHTML = "test" + element.dataset.keyword;
//   })
// }

let keywords = document.querySelectorAll(".keyword-container");

// let searchButton = document.querySelector("#serp_result_submit");
let searchButton = document.querySelector("#serp");
searchButton.addEventListener("click", function() {
  keywords.forEach(keyword => {
    checkRank(keyword);
  })

});

// Dynamic display of the form

let $serpInfoForm = document.querySelector(".serp-info-form");
let $addSerpButton = document.querySelector(".add-serp-button");
let $closeSerpFormButton = document.querySelector(".close-serp-form");

$serpInfoForm.style.display = "none";

$addSerpButton.addEventListener("click", function(){
  $addSerpButton.style.display = "none";
  $serpInfoForm.style.display = "flex";
})

$closeSerpFormButton.addEventListener("click", function(){
  $addSerpButton.style.display = "block";
  $serpInfoForm.style.display = "none";
})

function saveRank(serpInfoId, rank) {
  // the URL is carried by the template, so the route can change without touching this file
  let saveUrl = searchButton.dataset.serpSaveUrl;

  if (!saveUrl) {
    console.error("URL d'enregistrement absente (data-serp-save-url)");
    return;
  }

  let xhr = new XMLHttpRequest();
  xhr.open('POST', saveUrl, true);
  xhr.setRequestHeader('Content-Type', 'application/json');
  xhr.onload = function() {
    // the endpoint answers 201 Created
    if (xhr.status === 201) {
      console.log('Rang enregistré pour le mot clé ' + serpInfoId);
    } else {
      console.error('Échec de l\'enregistrement du rang (HTTP ' + xhr.status + ') : ' + xhr.responseText);
    }
  };
  xhr.onerror = function() {
    console.error("Échec de l'appel d'enregistrement du rang");
  };

  // the date is set server side, it is not the browser's business
  xhr.send(JSON.stringify({ serpInfo: Number(serpInfoId), googleRank: rank }));
}
