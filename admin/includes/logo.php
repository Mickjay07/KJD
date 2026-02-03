<?php
// Authentic San Francisco PRO logo inspired by iPhone 17 PRO design
?>
<style>
  .pro-logo {
    font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 900; /* SF Pro Display Black */
    font-size: 4rem;
    letter-spacing: -0.03em;
    line-height: 0.85;
    text-transform: uppercase;
    position: relative;
    display: inline-block;
    color: var(--kjd-dark-green);
    text-shadow: 0 2px 4px rgba(16,40,32,0.1);
    filter: drop-shadow(0 1px 2px rgba(16,40,32,0.1));
  }
  
  /* Individual letter styling with authentic cutouts like iPhone 17 PRO */
  .pro-logo .letter-p {
    position: relative;
  }
  
  .pro-logo .letter-p::after {
    content: '';
    position: absolute;
    top: 12%;
    right: 6%;
    width: 14%;
    height: 28%;
    background: var(--kjd-beige);
    border-radius: 1px;
    box-shadow: inset 0 1px 2px rgba(16,40,32,0.1);
  }
  
  .pro-logo .letter-r {
    position: relative;
  }
  
  .pro-logo .letter-r::after {
    content: '';
    position: absolute;
    top: 55%;
    right: 4%;
    width: 16%;
    height: 22%;
    background: var(--kjd-beige);
    border-radius: 1px;
    transform: rotate(12deg);
    box-shadow: inset 0 1px 2px rgba(16,40,32,0.1);
  }
  
  .pro-logo .letter-o {
    position: relative;
  }
  
  .pro-logo .letter-o::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 38%;
    height: 38%;
    background: var(--kjd-beige);
    border-radius: 4px;
    box-shadow: inset 0 1px 2px rgba(16,40,32,0.1);
  }
  
  /* Alternative clean version without cutouts */
  .pro-logo-clean {
    font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 900;
    font-size: 4rem;
    letter-spacing: -0.03em;
    line-height: 0.85;
    text-transform: uppercase;
    position: relative;
    display: inline-block;
    color: var(--kjd-dark-green);
    text-shadow: 0 2px 4px rgba(16,40,32,0.1);
    filter: drop-shadow(0 1px 2px rgba(16,40,32,0.1));
  }
  
  /* Gradient version */
  .pro-logo-gradient {
    font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-weight: 900;
    font-size: 4rem;
    letter-spacing: -0.03em;
    line-height: 0.85;
    text-transform: uppercase;
    position: relative;
    display: inline-block;
    background: linear-gradient(135deg, var(--kjd-dark-green), var(--kjd-earth-green));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 4px rgba(16,40,32,0.1);
    filter: drop-shadow(0 1px 2px rgba(16,40,32,0.1));
  }
</style>

<!-- Authentic PRO Logo with cutouts -->
<div class="pro-logo">
  <span class="letter-p">P</span><span class="letter-r">R</span><span class="letter-o">O</span>
</div>

<!-- Alternative versions -->
<!-- 
<div class="pro-logo-clean">PRO</div>
<div class="pro-logo-gradient">PRO</div>
-->
