import pandas as pd
import numpy as np
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

# Data source: https://www.kaggle.com/datasets/ahmedmohamed2003/cafe-sales-dirty-data-for-cleaning-training?resource=download

# Menu prices reference
MENU_PRICES = {
    'Coffee': 2.0,
    'Tea': 1.5,
    'Sandwich': 4.0,
    'Salad': 5.0,
    'Cake': 3.0,
    'Cookie': 1.0,
    'Smoothie': 4.0,
    'Juice': 3.0
}

def load_data():
    """Load the dirty cafe sales data"""
    print("Loading data...")
    df = pd.read_csv('dirty_cafe_sales.csv')
    print(f"Dataset loaded: {df.shape[0]} rows, {df.shape[1]} columns")
    return df

def perform_eda(df):
    """Perform Exploratory Data Analysis"""
    print("\n" + "="*50)
    print("EXPLORATORY DATA ANALYSIS")
    print("="*50)
    
    print(f"\nDataset shape: {df.shape}")
    print(f"Columns: {list(df.columns)}")
    
    print("\nData types:")
    print(df.dtypes)
    
    print("\nFirst 5 rows:")
    print(df.head())
    
    print("\nMissing/Invalid values (null/empty/ERROR/UNKNOWN):")
    missing_counts = df.isnull().sum()
    empty_counts = (df == '').sum()
    error_counts = (df == 'ERROR').sum()
    unknown_counts = (df == 'UNKNOWN').sum()
    total_problematic = missing_counts + empty_counts + error_counts + unknown_counts
    
    missing_df = pd.DataFrame({
        'Column': df.columns,
        'Null_Values': missing_counts,
        'Empty_Values': empty_counts,
        'ERROR_Values': error_counts,
        'UNKNOWN_Values': unknown_counts,
        'Total_Problematic': total_problematic,
        'Problematic_Percentage': (total_problematic / len(df)) * 100
    })
    print(missing_df)
    
    print("\nUnique values per column:")
    for col in df.columns:
        unique_values = df[col].nunique()
        print(f"{col}: {unique_values} unique values")
        
        # Show problematic values
        unique_vals = df[col].unique()
        problematic = [val for val in unique_vals if val in ['ERROR', 'UNKNOWN', '', np.nan]]
        if problematic:
            print(f"  - Problematic values found: {problematic}")
    
    # Check for calculation inconsistencies
    print("\nChecking calculation consistency (Quantity * Price Per Unit = Total Spent):")
    df_calc = df.copy()
    
    # Convert numeric columns, handling errors
    for col in ['Quantity', 'Price Per Unit', 'Total Spent']:
        df_calc[col] = pd.to_numeric(df_calc[col], errors='coerce')
    
    # Calculate expected total
    df_calc['Expected_Total'] = df_calc['Quantity'] * df_calc['Price Per Unit']
    df_calc['Calc_Error'] = ~np.isclose(df_calc['Total Spent'], df_calc['Expected_Total'], rtol=1e-09, equal_nan=True)
    
    calc_errors = df_calc['Calc_Error'].sum()
    print(f"Rows with calculation errors: {calc_errors}")
    
    return missing_df, calc_errors

def clean_data(df):
    """Main data cleaning function"""
    print("\n" + "="*50)
    print("DATA CLEANING")
    print("="*50)
    
    df_clean = df.copy()
    print(f"Starting with {len(df_clean)} rows")
    
    # 1. Handle missing values and replace ERROR/UNKNOWN with NaN
    print("\n1. Handling missing and invalid values...")
    
    # Replace empty strings and problematic values with NaN
    df_clean = df_clean.replace(['', 'ERROR', 'UNKNOWN'], np.nan)
    
    print("Missing values after cleaning:")
    missing_after = df_clean.isnull().sum()
    for col, count in missing_after.items():
        if count > 0:
            print(f"  {col}: {count} missing values ({count/len(df_clean)*100:.1f}%)")
    
    # 2. Clean Transaction ID - should never be missing
    print("\n2. Cleaning Transaction ID...")
    rows_before = len(df_clean)
    df_clean = df_clean.dropna(subset=['Transaction ID'])
    rows_after = len(df_clean)
    print(f"Removed {rows_before - rows_after} rows with missing Transaction ID")
    
    # 3. Clean Item column
    print("\n3. Cleaning Item column...")
    missing_items = df_clean['Item'].isnull().sum()
    print(f"Rows with missing items: {missing_items}")
    
    # For missing items, try to infer from price
    # Meaning: If there's menu item, then the price should be in the menu prices
    mask_missing_item = df_clean['Item'].isnull()
    if mask_missing_item.sum() > 0:
        print("Attempting to infer missing items from price...")
        for idx in df_clean[mask_missing_item].index:
            price = pd.to_numeric(df_clean.loc[idx, 'Price Per Unit'], errors='coerce')
            if not pd.isna(price):
                # Find matching item from menu
                for item, menu_price in MENU_PRICES.items():
                    if price == menu_price:
                        df_clean.loc[idx, 'Item'] = item
                        print(f"  Inferred item '{item}' for row {idx} based on price ${price}")
                        break
                    
    # Remove rows where item is still missing
    rows_before = len(df_clean)
    df_clean = df_clean.dropna(subset=['Item'])
    rows_after = len(df_clean)
    if rows_before != rows_after:
        print(f"Removed {rows_before - rows_after} rows with unresolvable missing items")
    
    # 4. Clean numeric columns
    print("\n4. Cleaning numeric columns...")
    
    # Clean Quantity
    df_clean['Quantity'] = pd.to_numeric(df_clean['Quantity'], errors='coerce')
    invalid_qty = df_clean['Quantity'].isnull() | (df_clean['Quantity'] <= 0)
    if invalid_qty.sum() > 0:
        print(f"Found {invalid_qty.sum()} invalid quantities")
        # Set invalid quantities to 1 (most common case)
        df_clean.loc[invalid_qty, 'Quantity'] = 1
        print("Set invalid quantities to 1")
    
    # Clean Price Per Unit using menu prices
    print("Cleaning Price Per Unit...")
    df_clean['Price Per Unit'] = pd.to_numeric(df_clean['Price Per Unit'], errors='coerce')
    
    # Fill missing prices from menu
    missing_price_mask = df_clean['Price Per Unit'].isnull()
    for idx in df_clean[missing_price_mask].index:
        item = df_clean.loc[idx, 'Item']
        if item in MENU_PRICES:
            df_clean.loc[idx, 'Price Per Unit'] = MENU_PRICES[item]
            print(f"  Fixed price for {item} at row {idx}")
    
    # Validate prices against menu
    for idx, row in df_clean.iterrows():
        item = row['Item']
        price = row['Price Per Unit']
        if item in MENU_PRICES and not pd.isna(price):
            if price != MENU_PRICES[item]:
                print(f"  Correcting price for {item} from ${price} to ${MENU_PRICES[item]} at row {idx}")
                df_clean.loc[idx, 'Price Per Unit'] = MENU_PRICES[item]
    
    # 5. Recalculate Total Spent for all rows
    print("\n5. Recalculating Total Spent for all rows...")
    
    # Since majority of prices and quantities have been corrected, recalculate all totals
    df_clean['Total Spent'] = df_clean['Quantity'] * df_clean['Price Per Unit']
    print(f"Recalculated Total Spent for all {len(df_clean)} rows")
    
    # 6. Clean Payment Method
    print("\n6. Cleaning Payment Method...")
    missing_payment = df_clean['Payment Method'].isnull().sum()
    if missing_payment > 0:
        print(f"Found {missing_payment} missing payment methods")
        # Fill with most common payment method
        most_common_payment = df_clean['Payment Method'].mode()[0] if len(df_clean['Payment Method'].mode()) > 0 else 'Cash'
        df_clean['Payment Method'] = df_clean['Payment Method'].fillna(most_common_payment)
        print(f"Filled missing payment methods with '{most_common_payment}'")
    
    # 7. Clean Location
    print("\n7. Cleaning Location...")
    missing_location = df_clean['Location'].isnull().sum()
    if missing_location > 0:
        print(f"Found {missing_location} missing locations")
        # Fill with most common location
        most_common_location = df_clean['Location'].mode()[0] if len(df_clean['Location'].mode()) > 0 else 'In-store'
        df_clean['Location'] = df_clean['Location'].fillna(most_common_location)
        print(f"Filled missing locations with '{most_common_location}'")
    
    # 8. Clean Transaction Date
    print("\n8. Cleaning Transaction Date...")
    missing_dates = df_clean['Transaction Date'].isnull().sum()
    if missing_dates > 0:
        print(f"Found {missing_dates} missing dates")
        # Remove rows with missing dates as they're critical
        rows_before = len(df_clean)
        df_clean = df_clean.dropna(subset=['Transaction Date'])
        rows_after = len(df_clean)
        print(f"Removed {rows_before - rows_after} rows with missing dates")
    
    # Validate date format
    try:
        df_clean['Transaction Date'] = pd.to_datetime(df_clean['Transaction Date'])
        print("Successfully converted dates to datetime format")
    except:
        print("Some dates may have formatting issues - will keep as text for manual review")
    
    print(f"\nCleaning complete! Final dataset: {len(df_clean)} rows")
    return df_clean

def generate_summary_report(df_original, df_clean):
    """Generate a summary report of the cleaning process"""
    print("\n" + "="*50)
    print("CLEANING SUMMARY REPORT")
    print("="*50)
    
    print(f"Original rows: {len(df_original)}")
    print(f"Cleaned rows: {len(df_clean)}")
    print(f"Rows removed: {len(df_original) - len(df_clean)}")
    print(f"Data retention rate: {len(df_clean)/len(df_original)*100:.2f}%")
    
    print(f"\nFinal data quality:")
    print("Missing values:")
    final_missing = df_clean.isnull().sum()
    for col, count in final_missing.items():
        print(f"  {col}: {count} missing values ({count/len(df_clean)*100:.1f}%)")
    
    if final_missing.sum() == 0:
        print("✅ No missing values in final dataset!")
    
    # Show unique values for categorical columns
    print("\nFinal unique values:")
    categorical_cols = ['Item', 'Payment Method', 'Location']
    for col in categorical_cols:
        if col in df_clean.columns:
            unique_vals = sorted(df_clean[col].unique())
            print(f"  {col}: {unique_vals}")

def main():
    """Main execution function"""
    # Load data
    df = load_data()
    
    # Perform EDA
    missing_report, calc_errors = perform_eda(df)
    
    # Clean data
    df_clean = clean_data(df)
    
    # Generate summary
    generate_summary_report(df, df_clean)
    
    # Save cleaned data
    print("\n" + "="*50)
    print("SAVING CLEANED DATA")
    print("="*50)
    
    # Save main cleaned dataset
    clean_filename = 'cleaned_cafe_sales.csv'
    df_clean.to_csv(clean_filename, index=False)
    print(f"✅ Cleaned data saved to: {clean_filename}")
    
    # Save EDA report
    eda_filename = 'eda_report_corrected.csv'
    missing_report.to_csv(eda_filename, index=False)
    print(f"✅ Corrected EDA report saved to: {eda_filename}")
    
    # Show sample of cleaned data
    print(f"\nSample of cleaned data (first 5 rows):")
    print(df_clean.head())
    
    print(f"\n🎉 Data cleaning completed successfully!")
    print(f"Final dataset contains {len(df_clean)} clean records")

if __name__ == "__main__":
    main()